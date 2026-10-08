<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Actions\Handoff\SendHandoffAdminMessageAction;
use Domain\Conversation\Data\Handoff\HandoffMessageResultPayloadData;
use Domain\Conversation\Data\Handoff\SendHandoffAdminMessageData;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Events\MessagePosted;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatMemory;
use Illuminate\Support\Facades\Event;

/**
 * An operator's reply in a handed-off conversation exists in three places —
 * the chat cache, the database row and the broadcast — and all three must
 * share ONE id. Read receipts are written by looking the row up by the cached
 * id; when the row got a different id, the visitor's "read" never persisted.
 */
beforeEach(function (): void {
    config(['cache.stores.chat' => ['driver' => 'array']]);
    Event::fake([MessagePosted::class]);
});

/**
 * @return array{0: User, 1: Session, 2: Conversation}
 */
function handoffMessageSetup(): array
{
    $owner = User::factory()->create();
    $agent = createAgent($owner);
    $session = Session::factory()->create(['agent_id' => $agent->id]);

    $conversation = Conversation::factory()->withoutTitle()->create([
        'chat_session_id' => $session->id,
        'source' => ConversationSource::Widget,
        'handoff_status' => 'human',
        'handoff_user_id' => $owner->id,
    ]);

    return [$owner, $session, $conversation];
}

it('stores the reply under the same external id it caches and broadcasts', function (): void {
    [$owner, , $conversation] = handoffMessageSetup();

    $message = app(SendHandoffAdminMessageAction::class)->execute(
        new SendHandoffAdminMessageData($conversation, $owner, 'Hi, I can help.'),
    );

    expect($message)->not->toBeNull();

    Event::assertDispatched(MessagePosted::class, fn (MessagePosted $e): bool => $e->messageId === $message->external_id
        && $e->role === 'agent');
});

it('persists the visitor read receipt for the operator reply', function (): void {
    [$owner, $session, $conversation] = handoffMessageSetup();

    $message = app(SendHandoffAdminMessageAction::class)->execute(
        new SendHandoffAdminMessageData($conversation, $owner, 'Hi, I can help.'),
    );

    $marked = app(ChatMemory::class)
        ->withContext($conversation->session->agent, $session->external_id, $conversation->external_id)
        ->markMessagesAsRead('user');

    expect($marked)->toBe(1)
        ->and($message->fresh()->read_at)->not->toBeNull();
});

it('returns the public id, never the internal key, to the dashboard', function (): void {
    [$owner, , $conversation] = handoffMessageSetup();

    $message = app(SendHandoffAdminMessageAction::class)->execute(
        new SendHandoffAdminMessageData($conversation, $owner, 'Hi'),
    );

    expect(HandoffMessageResultPayloadData::fromMessage($message)->id)->toBe($message->external_id);
});

it('refuses to post when the actor has not taken the conversation over', function (): void {
    [, , $conversation] = handoffMessageSetup();
    $stranger = User::factory()->create();

    $message = app(SendHandoffAdminMessageAction::class)->execute(
        new SendHandoffAdminMessageData($conversation, $stranger, 'Hi'),
    );

    expect($message)->toBeNull();
    Event::assertNotDispatched(MessagePosted::class);
});
