<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Events\MessageSent;
use Domain\Conversation\Actions\Conversations\StartConversationAction;
use Domain\Conversation\Actions\Messages\StoreUserMessageAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Data\Messages\UserTurnData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Events\SessionLocationUnknown;
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
 * Writing a visitor message to the chat log: the first message revives a
 * hidden or expired conversation with the full retention window but never
 * one someone deleted; a chatter is filled in on a session started without
 * one but never on a staff preview session (which names no chatter to
 * analytics); and the rows land all or nothing, with follow-up work only
 * after the commit.
 */
function sumaTurn(array $overrides = []): UserTurnData
{
    return UserTurnData::from(array_merge([
        'external_session_id' => (string) Str::uuid7(),
        'external_conversation_id' => (string) Str::uuid7(),
        'external_message_id' => (string) Str::uuid7(),
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

it('revives a hidden or expired empty conversation with the full retention window, but refuses one someone deleted', function (): void {
    $this->freezeSecond();
    config(['chatvia.retention.conversations_days' => 90]);
    $agent = Agent::factory()->create();
    $scope = new ChatScopeData($agent, (string) Str::uuid7());
    $start = fn (): string => app(StartConversationAction::class)->execute($scope, ConversationSource::Widget)->external_id;
    $send = fn (string $conversationId): Message => app(StoreUserMessageAction::class)->execute($agent, sumaTurn([
        'external_session_id' => $scope->sessionId,
        'external_conversation_id' => $conversationId,
    ]));
    $hiddenId = $start();
    $expiredId = $start();
    Conversation::query()->where('external_id', $expiredId)->sole()->delete();
    $deletedId = $start();
    $deleted = Conversation::query()->where('external_id', $deletedId)->sole();
    $deleted->update(['deleted_by' => 'user']);
    $deleted->delete();

    $send($hiddenId);
    $send($expiredId);

    $hidden = Conversation::query()->where('external_id', $hiddenId)->sole();
    $expired = Conversation::query()->where('external_id', $expiredId)->sole();
    expect($hidden->is_visible)->toBeTrue()
        ->and($hidden->expires_at->isSameDay(now()->addDays(90)))->toBeTrue()
        ->and($expired->is_visible)->toBeTrue()
        ->and($expired->messages()->count())->toBe(1)
        ->and(fn (): Message => $send($deletedId))->toThrow(ModelNotFoundException::class)
        ->and(Conversation::withTrashed()->where('external_id', $deletedId)->sole()->trashed())->toBeTrue();
});

it('backfills a chatter onto a session started without one, but never onto a staff preview session', function (): void {
    $owner = User::factory()->workspace()->create(['id' => sharedStaffAndChatterId()]);
    $agent = Agent::factory()->create(['workspace_id' => $owner->workspace()->id]);
    $chatter = Chatter::factory()->grantedTo($agent)->create(['id' => $owner->id]);
    $anonymousId = (string) Str::uuid7();
    $staffId = (string) Str::uuid7();
    Session::factory()->create(['external_id' => $anonymousId, 'agent_id' => $agent->id]);
    Session::factory()->create(['external_id' => $staffId, 'agent_id' => $agent->id, 'user_id' => $owner->id]);
    $staffTurn = sumaTurn(['external_session_id' => $staffId, 'chatter_id' => $chatter->id]);

    app(StoreUserMessageAction::class)->execute($agent, sumaTurn(['external_session_id' => $anonymousId, 'chatter_id' => $chatter->id]));
    app(StoreUserMessageAction::class)->execute($agent, $staffTurn);

    $backfilled = Session::query()->where('external_id', $anonymousId)->sole();
    $staff = Session::query()->where('external_id', $staffId)->sole();
    expect($backfilled->chatter_id)->toBe($chatter->id)
        ->and($backfilled->user_id)->toBeNull()
        ->and($staff->user_id)->toBe($owner->id)
        ->and($staff->chatter_id)->toBeNull();
    Event::assertDispatched(MessageSent::class, fn (MessageSent $event): bool => $event->messageExternalId === $staffTurn->external_message_id
        && $event->chatterId === null);
});

it('writes nothing and queues nothing when one of its writes fails', function (): void {
    Event::fake([MessageSent::class, SessionLocationUnknown::class]);

    $agent = Agent::factory()->create();
    $action = app(StoreUserMessageAction::class);

    $taken = $action->execute($agent, sumaTurn());
    Event::fake([MessageSent::class, SessionLocationUnknown::class]);

    $turn = sumaTurn([
        'external_message_id' => $taken->external_id,
        'ip_address' => '203.0.113.7',
    ]);

    expect(fn (): Message => $action->execute($agent, $turn))->toThrow(QueryException::class);

    expect(Session::query()->where('external_id', $turn->external_session_id)->exists())->toBeFalse()
        ->and(Conversation::query()->where('external_id', $turn->external_conversation_id)->exists())->toBeFalse();
    Event::assertNotDispatched(MessageSent::class);
    Event::assertNotDispatched(SessionLocationUnknown::class);
});
