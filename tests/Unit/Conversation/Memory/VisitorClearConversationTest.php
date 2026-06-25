<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Domain\Conversation\Services\Memory\ChatMemory;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Support\Str;

/**
 * The widget "clear chats" path is a VIEW RESET, not erasure: it hides the
 * conversation from the visitor's widget while leaving the customer's persisted
 * record fully intact (message content, no soft-delete, still visible in the
 * chat log). Contrast AdminDeleteConversationActionTest, which is the erasing
 * owner/admin path that nulls content and soft-deletes.
 */
beforeEach(function (): void {
    // ChatCache pins Cache::driver('chat') (redis in config); point it at the
    // array store for tests — same override the other Memory unit tests use.
    config(['cache.stores.chat' => ['driver' => 'array']]);
});

/**
 * @return array{0: Agent, 1: Session, 2: Conversation, 3: Message}
 */
function visitorClearSetup(): array
{
    $owner = User::factory()->create();
    $agent = createAgent($owner);

    $session = Session::factory()->create(['agent_id' => $agent->id]);

    $conversation = Conversation::factory()->withoutTitle()->create([
        'chat_session_id' => $session->id,
        'agent_id' => $agent->id,
        'source' => ConversationSource::Widget,
        'is_visible' => true,
    ]);

    $message = Message::create([
        'external_id' => (string) Str::uuid(),
        'chat_conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'What are your opening hours?',
    ]);

    return [$agent, $session, $conversation, $message];
}

it('hides a cleared conversation from the visitor but keeps the customer record intact', function (): void {
    [$agent, $session, $conversation, $message] = visitorClearSetup();

    app(ChatMemory::class)
        ->withContext($agent, $session->external_id, $conversation->external_id)
        ->clearConversationForVisitor();

    // Customer side: the row is NOT soft-deleted, content is preserved, and it
    // is stamped as cleared by the visitor.
    $fresh = Conversation::query()->find($conversation->id);

    expect($fresh)->not->toBeNull()
        ->and($fresh->trashed())->toBeFalse()
        ->and($fresh->cleared_at)->not->toBeNull()
        ->and($fresh->deleted_by)->toBe('user')
        ->and($message->fresh()->content)->toBe('What are your opening hours?');
});

it('drops a cleared conversation from the visitor session rebuild', function (): void {
    [$agent, $session, $conversation] = visitorClearSetup();

    $memory = app(ChatMemory::class)->withContext($agent, $session->external_id);

    // Cold the cache so the next read rebuilds the list from the database,
    // exercising the cleared_at filter in loadConversationsFromDatabase().
    $memory->setConversationId($conversation->external_id)->clearConversationForVisitor();

    $ids = collect(
        app(ChatMemory::class)
            ->withContext($agent, $session->external_id)
            ->getConversationsForCurrentSession()
    )->pluck('id');

    expect($ids)->not->toContain($conversation->external_id);
});

it('stops the widget read side from resurfacing a cleared conversation', function (): void {
    [$agent, $session, $conversation] = visitorClearSetup();

    // Resolvable before the clear...
    expect(app(WidgetVerification::class)->verifyConversationId($conversation->external_id, $agent))
        ->not->toBeNull();

    app(ChatMemory::class)
        ->withContext($agent, $session->external_id, $conversation->external_id)
        ->clearConversationForVisitor();

    // ...and gone from the widget's read path afterwards (message fetch,
    // bootstrap restore, form submit all funnel through verifyConversationId).
    expect(app(WidgetVerification::class)->verifyConversationId($conversation->external_id, $agent))
        ->toBeNull();
});

it('clears every conversation in the session without erasing any of them', function (): void {
    [$agent, $session, $first, $firstMessage] = visitorClearSetup();

    $second = Conversation::factory()->withoutTitle()->create([
        'chat_session_id' => $session->id,
        'agent_id' => $agent->id,
        'source' => ConversationSource::Widget,
        'is_visible' => true,
    ]);

    app(ChatMemory::class)
        ->withContext($agent, $session->external_id)
        ->clearAllConversationsForVisitor();

    // Both rows survive (not trashed), both stamped cleared, content preserved.
    foreach ([$first->id, $second->id] as $id) {
        $row = Conversation::query()->find($id);

        expect($row)->not->toBeNull()
            ->and($row->trashed())->toBeFalse()
            ->and($row->cleared_at)->not->toBeNull()
            ->and($row->deleted_by)->toBe('user');
    }

    expect($firstMessage->fresh()->content)->toBe('What are your opening hours?');

    // The customer's chat-log query (default scope, agent-scoped) still sees both.
    expect(Conversation::query()->where('agent_id', $agent->id)->count())->toBe(2);
});
