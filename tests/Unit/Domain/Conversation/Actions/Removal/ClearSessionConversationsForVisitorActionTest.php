<?php

use Domain\Conversation\Actions\Removal\ClearSessionConversationsForVisitorAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;

/**
 * The widget "clear chats" for a whole session is a view reset, not erasure:
 * every conversation of the session is hidden from the visitor and stays in
 * the customer's chat log with its text.
 */
it('clears every conversation in the session without erasing any of them', function (): void {
    $session = Session::factory()->create();
    [$first, $second] = Conversation::factory()->count(2)->inSession($session)->visible()->create();
    $message = Message::factory()->fromUser()->create(['chat_conversation_id' => $first->id, 'content' => 'What are your opening hours?']);

    app(ClearSessionConversationsForVisitorAction::class)->execute(new ChatScopeData($session->agent, $session->external_id));

    // Not soft-deleted: the customer's chat log still lists both.
    $rows = Conversation::query()->whereKey([$first->id, $second->id])->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->whereNull('cleared_at'))->toBeEmpty()
        ->and($rows->pluck('deleted_by')->all())->toBe(['user', 'user'])
        ->and($message->fresh()->content)->toBe('What are your opening hours?');
});
