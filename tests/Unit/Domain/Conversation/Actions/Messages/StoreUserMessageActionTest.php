<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Actions\MessageLimits\SetMessageLimitAction;
use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Events\MessageSent;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Domain\Conversation\Actions\Messages\StoreUserMessageAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Messages\UserTurnData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Events\SessionLocationUnknown;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * Writing a visitor message to the chat log: the message, its session and
 * conversation and the claimed uploads land in one transaction; the location
 * request and the analytics event follow the commit. Monthly limits are not
 * counted here — the turn counts them as it runs — and only a chatter session
 * names a chatter to analytics; a staff preview session (`user_id`) does not.
 */
function storeUserTurn(array $overrides = []): UserTurnData
{
    return UserTurnData::from(array_merge([
        'external_session_id' => (string) Str::uuid(),
        'external_conversation_id' => (string) Str::uuid(),
        'external_message_id' => (string) Str::uuid(),
        'llm_id' => null,
        'message' => 'Hello, can you help me with my order?',
        'sent_at' => Carbon::parse('2026-06-05 10:00:00'),
        'source' => 'widget',
    ], $overrides));
}

beforeEach(function (): void {
    // Only the analytics event is faked: a blanket Event::fake() would also
    // stop the model events that fill external ids.
    Event::fake([MessageSent::class]);
    Bus::fake();
});

it('stores a visitor message and announces it to analytics', function (): void {
    $agent = Agent::factory()->create();
    $turn = storeUserTurn();

    $message = app(StoreUserMessageAction::class)->execute($agent, $turn);

    expect($message)->toBeInstanceOf(Message::class)
        ->and($message->role)->toBe(MessageRole::User)
        ->and($message->content)->toBe($turn->message)
        ->and($message->external_id)->toBe($turn->external_message_id)
        ->and($message->llm_id)->toBeNull()
        ->and($message->prompt_tokens)->toBeNull()
        ->and($message->completion_tokens)->toBeNull();

    Event::assertDispatched(MessageSent::class, fn (MessageSent $event): bool => $event->agentId === $agent->id
        && $event->role === 'user'
        && $event->messageExternalId === $turn->external_message_id
        && $event->promptTokens === null
        && $event->completionTokens === null);
});

it('makes a hidden conversation visible with the full retention window on its first message', function (): void {
    config(['chatvia.retention.conversations_days' => 90]);
    $agent = Agent::factory()->create();
    $scope = new ChatScopeData($agent, (string) Str::uuid());
    $conversationId = app(StartConversationAction::class)->execute($scope, ConversationSource::Widget)->external_id;

    $started = Conversation::query()->where('external_id', $conversationId)->sole();
    expect($started->is_visible)->toBeFalse()
        ->and($started->expires_at->isSameDay(now()->addDays(Conversation::HIDDEN_LIFETIME_DAYS)))->toBeTrue();

    app(StoreUserMessageAction::class)->execute($agent, storeUserTurn([
        'external_session_id' => $scope->sessionId,
        'external_conversation_id' => $conversationId,
    ]));

    $used = $started->fresh();
    expect($used->is_visible)->toBeTrue()
        ->and($used->expires_at->isSameDay(now()->addDays(90)))->toBeTrue();
});

it('brings back an empty conversation that expired while the widget stayed open', function (): void {
    config(['chatvia.retention.conversations_days' => 90]);
    $agent = Agent::factory()->create();
    $scope = new ChatScopeData($agent, (string) Str::uuid());
    $conversationId = app(StartConversationAction::class)->execute($scope, ConversationSource::Widget)->external_id;
    Conversation::query()->where('external_id', $conversationId)->sole()->delete();

    app(StoreUserMessageAction::class)->execute($agent, storeUserTurn([
        'external_session_id' => $scope->sessionId,
        'external_conversation_id' => $conversationId,
    ]));

    $revived = Conversation::query()->where('external_id', $conversationId)->sole();
    expect($revived->is_visible)->toBeTrue()
        ->and($revived->messages()->count())->toBe(1);
});

it('refuses a message to a conversation someone deleted, without reusing its id', function (): void {
    $agent = Agent::factory()->create();
    $scope = new ChatScopeData($agent, (string) Str::uuid());
    $conversationId = app(StartConversationAction::class)->execute($scope, ConversationSource::Widget)->external_id;
    $conversation = Conversation::query()->where('external_id', $conversationId)->sole();
    $conversation->update(['deleted_by' => 'user']);
    $conversation->delete();

    expect(fn () => app(StoreUserMessageAction::class)->execute($agent, storeUserTurn([
        'external_session_id' => $scope->sessionId,
        'external_conversation_id' => $conversationId,
    ])))->toThrow(ModelNotFoundException::class);

    expect(Conversation::withTrashed()->where('external_id', $conversationId)->sole()->trashed())->toBeTrue();
});

it('gives a conversation created by its first message the full retention window', function (): void {
    config(['chatvia.retention.conversations_days' => 90]);
    $agent = Agent::factory()->create();
    $turn = storeUserTurn();

    app(StoreUserMessageAction::class)->execute($agent, $turn);

    $conversation = Conversation::query()->where('external_id', $turn->external_conversation_id)->sole();
    expect($conversation->is_visible)->toBeTrue()
        ->and($conversation->expires_at->isSameDay(now()->addDays(90)))->toBeTrue();
});

it('backfills the chatter onto a session that was started without one', function (): void {
    // The hosted chat starts the session row before it knows the chatter, so
    // the session is keyed on (external_id, agent_id) and the chatter is
    // filled in afterwards.
    $agent = Agent::factory()->create();
    $chatter = Chatter::factory()->grantedTo($agent)->create();

    $sessionExternalId = (string) Str::uuid();
    Session::factory()->create(['external_id' => $sessionExternalId, 'agent_id' => $agent->id]);

    app(StoreUserMessageAction::class)->execute($agent, storeUserTurn([
        'external_session_id' => $sessionExternalId,
        'chatter_id' => $chatter->id,
    ]));

    $session = Session::query()->where('external_id', $sessionExternalId)->sole();

    expect($session->chatter_id)->toBe($chatter->id)
        ->and($session->user_id)->toBeNull();
});

it('never puts a chatter on a staff preview session', function (): void {
    // A session has at most one viewer: a staff session keeps its staff
    // account even if a turn names a chatter.
    $agent = Agent::factory()->create();
    $chatter = Chatter::factory()->grantedTo($agent)->create();

    $sessionExternalId = (string) Str::uuid();
    Session::factory()->create(['external_id' => $sessionExternalId, 'agent_id' => $agent->id, 'user_id' => $agent->workspace->owner->id]);

    app(StoreUserMessageAction::class)->execute($agent, storeUserTurn([
        'external_session_id' => $sessionExternalId,
        'chatter_id' => $chatter->id,
    ]));

    $session = Session::query()->where('external_id', $sessionExternalId)->sole();

    expect($session->user_id)->toBe($agent->workspace->owner->id)
        ->and($session->chatter_id)->toBeNull();
});

it('asks for the location with the request-resolved country', function (): void {
    // The Cloudflare-aware country is captured at request time and must reach
    // the queued listener through the event.
    Event::fake([MessageSent::class, SessionLocationUnknown::class]);

    $agent = Agent::factory()->create();

    app(StoreUserMessageAction::class)->execute($agent, storeUserTurn([
        'ip_address' => '203.0.113.10',
        'country' => 'iq',
    ]));

    Event::assertDispatched(
        SessionLocationUnknown::class,
        fn (SessionLocationUnknown $event): bool => $event->countryCode === 'iq',
    );
});

it('counts no message against any monthly limit (the turn counts, not the chat log)', function (): void {
    // Counting lives in AddMessageToConversationAction, on the turn itself,
    // so a retried or duplicate chat-log write can never count twice.
    $agent = Agent::factory()->create();
    $chatter = Chatter::factory()->grantedTo($agent)->create();
    app(SetMessageLimitAction::class)->execute($chatter, 5);
    app(SetMessageLimitAction::class)->execute($agent->workspace, 5);

    app(StoreUserMessageAction::class)->execute($agent, storeUserTurn(['chatter_id' => $chatter->id]));

    expect(MessageLimit::query()->where('chatter_id', $chatter->id)->value('messages_used'))->toBe(0)
        ->and(MessageLimit::query()->where('workspace_id', $agent->workspace_id)->value('messages_used'))->toBe(0);
});

it('names the chatter of a chatter session to analytics', function (): void {
    $agent = Agent::factory()->create();
    $chatter = Chatter::factory()->grantedTo($agent)->create();
    $turn = storeUserTurn(['chatter_id' => $chatter->id]);

    app(StoreUserMessageAction::class)->execute($agent, $turn);

    Event::assertDispatched(MessageSent::class, fn (MessageSent $event): bool => $event->messageExternalId === $turn->external_message_id
        && $event->chatterId === $chatter->id);
});

it('names no chatter to analytics for a staff preview session', function (): void {
    // Same integer id again: the staff viewer must not be reported as the
    // chatter who happens to share it.
    $owner = User::factory()->workspace()->create(['id' => sharedStaffAndChatterId()]);
    $agent = Agent::factory()->create(['workspace_id' => $owner->workspace()->id]);
    Chatter::factory()->grantedTo($agent)->create(['id' => $owner->id]);

    $sessionExternalId = (string) Str::uuid();
    Session::factory()->create(['external_id' => $sessionExternalId, 'agent_id' => $agent->id, 'user_id' => $owner->id]);
    $turn = storeUserTurn(['external_session_id' => $sessionExternalId]);

    app(StoreUserMessageAction::class)->execute($agent, $turn);

    Event::assertDispatched(MessageSent::class, fn (MessageSent $event): bool => $event->messageExternalId === $turn->external_message_id
        && $event->chatterId === null);
});

it('ties the files uploaded into the conversation to the message', function (): void {
    $agent = Agent::factory()->create();
    $turn = storeUserTurn();

    // First message creates the session and conversation.
    app(StoreUserMessageAction::class)->execute($agent, $turn);
    $conversation = Conversation::query()->firstWhere('external_id', $turn->external_conversation_id);

    $upload = Attachment::create([
        'workspace_id' => $conversation->workspace_id,
        'chat_conversation_id' => $conversation->id,
        'filepath' => 'workspaces/x/agents/y/attachments/doc.pdf',
        'filename' => 'doc.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 8,
    ]);

    $message = app(StoreUserMessageAction::class)->execute($agent, storeUserTurn([
        'external_session_id' => $turn->external_session_id,
        'external_conversation_id' => $turn->external_conversation_id,
    ]));

    expect($upload->fresh()->chat_message_id)->toBe($message->id);
});

it('writes nothing and queues nothing when one of its writes fails', function (): void {
    Event::fake([MessageSent::class, SessionLocationUnknown::class]);

    $agent = Agent::factory()->create();
    $action = app(StoreUserMessageAction::class);

    // An existing message already owns this external id, so the message
    // insert fails AFTER the session and conversation rows were written.
    $taken = $action->execute($agent, storeUserTurn());
    Event::fake([MessageSent::class, SessionLocationUnknown::class]);

    $turn = storeUserTurn([
        'external_message_id' => $taken->external_id,
        'ip_address' => '203.0.113.7',
    ]);

    expect(fn (): Message => $action->execute($agent, $turn))->toThrow(QueryException::class);

    // All-or-nothing: the half-written session and conversation are rolled back…
    expect(Session::query()->where('external_id', $turn->external_session_id)->exists())->toBeFalse()
        ->and(Conversation::query()->where('external_id', $turn->external_conversation_id)->exists())->toBeFalse();

    // …and no follow-up work was queued for rows that never committed.
    Event::assertNotDispatched(MessageSent::class);
    Event::assertNotDispatched(SessionLocationUnknown::class);
});
