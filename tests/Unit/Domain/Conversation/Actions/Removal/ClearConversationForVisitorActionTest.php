<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Removal\ClearConversationForVisitorAction;
use Domain\Conversation\Actions\Removal\ClearSessionConversationsForVisitorAction;
use Domain\Conversation\Data\Memory\ChatScopeData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Widget\Services\WidgetVerification;

/**
 * The widget "clear chats" path is a VIEW RESET, not erasure: it hides the
 * conversation from the visitor's widget while leaving the customer's persisted
 * record fully intact (message content, no soft-delete, still visible in the
 * chat log). Contrast AdminDeleteConversationActionTest, which is the erasing
 * owner/admin path that nulls content and soft-deletes. Clearing a whole
 * session works the same way for each of its conversations.
 */
/**
 * @return array{0: Agent, 1: Session, 2: Conversation, 3: Message}
 */
function ccfvSetup(): array
{
    $conversation = Conversation::factory()->forAgent(createAgent(User::factory()->create()))->visible()->create();
    $message = Message::factory()->fromUser()->create([
        'chat_conversation_id' => $conversation->id,
        'content' => 'What are your opening hours?',
    ]);

    return [$conversation->agent, $conversation->session, $conversation, $message];
}

it('hides a cleared conversation from the visitor but keeps the customer record intact', function (): void {
    [$agent, $session, $conversation, $message] = ccfvSetup();

    app(ClearConversationForVisitorAction::class)->execute(
        new ChatScopeData($agent, $session->external_id, $conversation->external_id),
    );

    // Customer side: the row is NOT soft-deleted, content is preserved, and it
    // is stamped as cleared by the visitor.
    $fresh = Conversation::query()->find($conversation->id);

    expect($fresh)->not->toBeNull()
        ->and($fresh->trashed())->toBeFalse()
        ->and($fresh->cleared_at)->not->toBeNull()
        ->and($fresh->deleted_by)->toBe('user')
        ->and($message->fresh()->content)->toBe('What are your opening hours?');
});

it('does not clear a conversation of another session or agent', function (): void {
    [$agent, , $conversation] = ccfvSetup();
    $otherSession = Session::factory()->create(['agent_id' => $agent->id]);
    $clear = app(ClearConversationForVisitorAction::class);

    // The conversation id replayed from another visitor's session, and under another agent.
    $clear->execute(new ChatScopeData($agent, $otherSession->external_id, $conversation->external_id));
    $clear->execute(new ChatScopeData(Agent::factory()->create(), $conversation->session->external_id, $conversation->external_id));

    $fresh = $conversation->fresh();

    expect($fresh->cleared_at)->toBeNull()
        ->and($fresh->deleted_by)->toBeNull();
});

it('drops a cleared conversation from the visitor session rebuild', function (): void {
    [$agent, $session, $conversation] = ccfvSetup();

    // The clear drops the conversation's cache, so the next read rebuilds
    // the list from the database and exercises the cleared_at filter.
    app(ClearConversationForVisitorAction::class)->execute(
        new ChatScopeData($agent, $session->external_id, $conversation->external_id),
    );

    $ids = collect(
        app(ChatMemory::class)
            ->withContext($agent, $session->external_id)
            ->getConversationsForCurrentSession()
    )->pluck('id');

    expect($ids)->not->toContain($conversation->external_id);
});

it('stops the widget read side from resurfacing a cleared conversation', function (): void {
    [$agent, $session, $conversation] = ccfvSetup();

    // Resolvable before the clear...
    expect(app(WidgetVerification::class)->verifyConversationId($conversation->external_id, $agent))
        ->not->toBeNull();

    app(ClearConversationForVisitorAction::class)->execute(
        new ChatScopeData($agent, $session->external_id, $conversation->external_id),
    );

    // ...and gone from the widget's read path afterwards (message fetch,
    // bootstrap restore, form submit all funnel through verifyConversationId).
    expect(app(WidgetVerification::class)->verifyConversationId($conversation->external_id, $agent))
        ->toBeNull();
});

it('clears every conversation in the session without erasing any of them', function (): void {
    $session = Session::factory()->create();
    [$first, $second] = Conversation::factory()->count(2)->inSession($session)->visible()->create();
    $message = Message::factory()->fromUser()->create(['chat_conversation_id' => $first->id, 'content' => 'What are your opening hours?']);

    app(ClearSessionConversationsForVisitorAction::class)->execute(new ChatScopeData($session->agent, $session->external_id));

    $rows = Conversation::query()->whereKey([$first->id, $second->id])->get();

    expect($rows)->toHaveCount(2)
        ->and($rows->whereNull('cleared_at'))->toBeEmpty()
        ->and($rows->pluck('deleted_by')->all())->toBe(['user', 'user'])
        ->and($message->fresh()->content)->toBe('What are your opening hours?');
});
