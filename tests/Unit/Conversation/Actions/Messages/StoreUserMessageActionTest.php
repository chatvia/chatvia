<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Events\MessageSent;
use Domain\Billing\Actions\ChatterAllowance\SetChatterMessageLimitAction;
use Domain\Billing\Services\ChatterMessageAllowance;
use Domain\Conversation\Actions\Messages\StoreUserMessageAction;
use Domain\Conversation\Data\Messages\UserTurnData;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Events\SessionLocationUnknown;
use Domain\Conversation\Models\Attachment;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;

/**
 * Writing a visitor message to the chat log: the message, its session and
 * conversation, the claimed uploads and the chatter's monthly count land in
 * one transaction; the location request and the analytics event follow the
 * commit.
 */
function userTurn(array $overrides = []): UserTurnData
{
    return UserTurnData::from(array_merge([
        'external_session_id' => (string) Str::uuid(),
        'external_conversation_id' => (string) Str::uuid(),
        'external_message_id' => (string) Str::uuid(),
        'model' => '',
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
    $turn = userTurn();

    $message = app(StoreUserMessageAction::class)->execute($agent, $turn);

    expect($message)->toBeInstanceOf(Message::class)
        ->and($message->role)->toBe(MessageRole::User)
        ->and($message->content)->toBe($turn->message)
        ->and($message->external_id)->toBe($turn->external_message_id)
        ->and($message->llm_id)->toBeNull()
        ->and($message->cost_in_micro_cents)->toBeNull()
        ->and($message->prompt_tokens)->toBeNull()
        ->and($message->completion_tokens)->toBeNull();

    Event::assertDispatched(MessageSent::class, fn (MessageSent $event): bool => $event->agentId === $agent->id
        && $event->role === 'user'
        && $event->messageExternalId === $turn->external_message_id
        && $event->promptTokens === null
        && $event->completionTokens === null);
});

it('backfills the chatter onto a session that was started without one', function (): void {
    // The hosted chat starts the session row before it knows the chatter, so
    // the session is keyed on (external_id, agent_id) and the chatter is
    // filled in afterwards.
    $agent = Agent::factory()->create();
    $chatter = User::factory()->chatter(User::factory()->workspace()->create())->create();

    $sessionExternalId = (string) Str::uuid();
    Session::create(['external_id' => $sessionExternalId, 'agent_id' => $agent->id]);

    app(StoreUserMessageAction::class)->execute($agent, userTurn([
        'external_session_id' => $sessionExternalId,
        'user_id' => $chatter->id,
    ]));

    expect(Session::where('external_id', $sessionExternalId)->value('user_id'))->toBe($chatter->id);
});

it('asks for the location with the request-resolved country', function (): void {
    // The Cloudflare-aware country is captured at request time and must reach
    // the queued listener through the event.
    Event::fake([MessageSent::class, SessionLocationUnknown::class]);

    $agent = Agent::factory()->create();

    app(StoreUserMessageAction::class)->execute($agent, userTurn([
        'ip_address' => '203.0.113.10',
        'country' => 'iq',
    ]));

    Event::assertDispatched(
        SessionLocationUnknown::class,
        fn (SessionLocationUnknown $event): bool => $event->countryCode === 'iq',
    );
});

it('counts a chatter message against their monthly limit', function (): void {
    $agent = Agent::factory()->create();
    $chatter = User::factory()->chatter(User::factory()->workspace()->create())->create();
    app(SetChatterMessageLimitAction::class)->execute($chatter, 5);
    $allowance = app(ChatterMessageAllowance::class);

    app(StoreUserMessageAction::class)->execute($agent, userTurn(['user_id' => $chatter->id]));

    expect($allowance->usedThisPeriod($chatter))->toBe(1);
});

it('ties the files uploaded into the conversation to the message', function (): void {
    $agent = Agent::factory()->create();
    $turn = userTurn();

    // First message creates the session and conversation.
    app(StoreUserMessageAction::class)->execute($agent, $turn);
    $conversation = Conversation::query()->firstWhere('external_id', $turn->external_conversation_id);

    $upload = Attachment::create([
        'chat_conversation_id' => $conversation->id,
        'filepath' => 'users/x/agents/y/attachments/doc.pdf',
        'filename' => 'doc.pdf',
        'mime_type' => 'application/pdf',
        'size_bytes' => 8,
    ]);

    $message = app(StoreUserMessageAction::class)->execute($agent, userTurn([
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
    $taken = $action->execute($agent, userTurn());
    Event::fake([MessageSent::class, SessionLocationUnknown::class]);

    $turn = userTurn([
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
