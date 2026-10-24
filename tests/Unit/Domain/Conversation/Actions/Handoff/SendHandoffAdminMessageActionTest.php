<?php

use App\Identity\Workspace\Models\User;
use Domain\Conversation\Actions\Activity\MarkMessagesReadAction;
use Domain\Conversation\Actions\Handoff\SendHandoffAdminMessageAction;
use Domain\Conversation\Data\Handoff\SendHandoffAdminMessageData;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Enums\HandoffStatus;
use Domain\Conversation\Enums\MessageReader;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Events\MessagePosted;
use Domain\Conversation\Exceptions\ConversationNotHeldException;
use Domain\Conversation\Models\Conversation;
use Illuminate\Support\Facades\Event;

/**
 * An operator's reply in a handed-off conversation exists in three places —
 * the chat cache, the database row and the broadcast — and all three must
 * share ONE id. Read receipts are written by looking the row up by the cached
 * id; when the row got a different id, the visitor's "read" never persisted.
 */
beforeEach(function (): void {
    Event::fake([MessagePosted::class]);
});

/** A widget conversation the agent's owner holds. */
function shamHeldConversation(): Conversation
{
    $agent = createAgent(User::factory()->create());

    return Conversation::factory()->forAgent($agent)->handedOff(HandoffStatus::Human, $agent->workspace->owner)->create();
}

it('stores the reply under the same external id it caches and broadcasts', function (): void {
    $conversation = shamHeldConversation();
    $owner = $conversation->agent->workspace->owner;

    $message = app(SendHandoffAdminMessageAction::class)->execute(
        new SendHandoffAdminMessageData($conversation, $owner, 'Hi, I can help.'),
    );

    expect($message)->not->toBeNull()
        ->and($message->role)->toBe(MessageRole::Operator);

    Event::assertDispatched(MessagePosted::class, fn (MessagePosted $e): bool => $e->messageId === $message->external_id
        && $e->role === MessageRole::Operator->value);
});

it('persists the visitor read receipt for the operator reply', function (): void {
    $this->freezeSecond();
    $conversation = shamHeldConversation();
    $owner = $conversation->agent->workspace->owner;

    $message = app(SendHandoffAdminMessageAction::class)->execute(
        new SendHandoffAdminMessageData($conversation, $owner, 'Hi, I can help.'),
    );

    $marked = app(MarkMessagesReadAction::class)->execute(
        new ChatScopeData($conversation->agent, $conversation->session->external_id, $conversation->external_id),
        MessageReader::Visitor,
    );

    expect($marked)->toBe(1)
        ->and($message->fresh()->read_at?->equalTo(now()))->toBeTrue();
});

it('refuses to post when the actor has not taken the conversation over', function (): void {
    $conversation = shamHeldConversation();
    $stranger = User::factory()->create();

    expect(fn (): mixed => app(SendHandoffAdminMessageAction::class)->execute(
        new SendHandoffAdminMessageData($conversation, $stranger, 'Hi'),
    ))->toThrow(ConversationNotHeldException::class);

    Event::assertNotDispatched(MessagePosted::class);
});
