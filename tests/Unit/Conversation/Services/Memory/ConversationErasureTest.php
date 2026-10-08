<?php

use Domain\Agent\Models\Agent;
use Domain\Conversation\Enums\ConversationSource;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session as ChatSession;
use Domain\Conversation\Services\Memory\ChatCache;
use Domain\Conversation\Services\Memory\ConversationManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Erasing a conversation must erase every copy of the message text. Besides
 * `content`, each message keeps a BM25 token vector of its text
 * (`content_bm25`) for search. The erase paths use bulk updates, which skip
 * the Message::saving hook that normally keeps the two in sync — so they must
 * clear `content_bm25` themselves.
 *
 * REQUIRES the migrated test DB (vchord_bm25 / tokenizer extensions).
 */
beforeEach(function (): void {
    config(['cache.stores.chat' => ['driver' => 'array']]);
});

/**
 * @return array{0: Agent, 1: ChatSession, 2: Conversation, 3: Message}
 */
function erasureSetup(): array
{
    $agent = Agent::factory()->create();
    $session = ChatSession::create(['agent_id' => $agent->id]);

    $conversation = Conversation::create([
        'chat_session_id' => $session->id,
        'agent_id' => $agent->id,
        'external_id' => (string) Str::uuid(),
        'source' => ConversationSource::HostedChat,
        'title' => 'Card question',
        'is_visible' => true,
    ]);

    $message = Message::create([
        'external_id' => (string) Str::uuid(),
        'chat_conversation_id' => $conversation->id,
        'role' => 'user',
        'content' => 'My card number is 4242 4242 4242 4242',
    ]);

    return [$agent, $session, $conversation, $message];
}

function erasureManager(ChatSession $session, Agent $agent): ConversationManager
{
    return new ConversationManager(new ChatCache($session->external_id, $agent), $session->external_id);
}

function erasureRow(Message $message): object
{
    return DB::table('chat_messages')->where('id', $message->id)->first(['content', 'content_bm25']);
}

it('clears the BM25 copy of the text when one conversation is erased', function (): void {
    [$agent, $session, $conversation, $message] = erasureSetup();
    expect(erasureRow($message)->content_bm25)->not->toBeNull();

    erasureManager($session, $agent)->deleteConversation($conversation->external_id, 'admin');

    $row = erasureRow($message);
    expect($row->content)->toBeNull()
        ->and($row->content_bm25)->toBeNull()
        ->and(Conversation::withTrashed()->find($conversation->id)->trashed())->toBeTrue();
});

it('clears the BM25 copy of the text when every conversation of a session is erased', function (): void {
    [$agent, $session, , $message] = erasureSetup();

    erasureManager($session, $agent)->deleteAllConversations('user');

    $row = erasureRow($message);
    expect($row->content)->toBeNull()
        ->and($row->content_bm25)->toBeNull();
});

it('treats erasing an already-deleted conversation as a no-op', function (): void {
    [$agent, $session, $conversation] = erasureSetup();
    $manager = erasureManager($session, $agent);

    $manager->deleteConversation($conversation->external_id);

    // A repeated request must not crash on the missing (soft-deleted) row.
    $manager->deleteConversation($conversation->external_id);
    $manager->deleteConversation((string) Str::uuid());

    expect(Conversation::withTrashed()->find($conversation->id)->trashed())->toBeTrue();
});

it('the data migration clears leftover BM25 copies of already-erased messages only', function (): void {
    [, , $conversation, $erased] = erasureSetup();
    $kept = Message::create([
        'external_id' => (string) Str::uuid(),
        'chat_conversation_id' => $conversation->id,
        'role' => 'assistant',
        'content' => 'Your order has shipped.',
    ]);

    // How the old erase path left rows: content gone, BM25 copy still there.
    DB::table('chat_messages')->where('id', $erased->id)->update(['content' => null]);
    expect(erasureRow($erased)->content_bm25)->not->toBeNull();

    $migration = require database_path('migrations/2026_10_08_174213_clear_bm25_copy_of_erased_chat_messages.php');
    $migration->up();

    expect(erasureRow($erased)->content_bm25)->toBeNull()
        ->and(erasureRow($kept)->content_bm25)->not->toBeNull();
});
