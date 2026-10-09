<?php

namespace App\Http\Controllers\Chat\Conversations;

use App\Http\Chat\HostedChatSession;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Widget\Services\WidgetVerification;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Search the current chat session's conversations from the sidebar palette.
 *
 * Matches on both the conversation title (encrypted at rest, so compared in
 * memory after the model decrypts it) and the message content (BM25 over
 * content_bm25 — see MessageQueryBuilder::searchContent). Title hits lead,
 * then content hits. Returns the same {id, title, timestamp} shape as the
 * conversations index so the search dialog renders the results directly.
 */
class ConversationSearchController
{
    private const int MAX_RESULTS = 50;

    private const int MAX_SEARCHED_CONVERSATIONS = 1000;

    /**
     * @return array<int, array{id: string, title: string, timestamp: int}>
     */
    public function __invoke(Request $request, HostedChatSession $chatSession, WidgetVerification $verification): array
    {
        $term = trim((string) $request->query('q', ''));
        if ($term === '') {
            return [];
        }

        $agent = current_agent();

        // A search is a read: look up the visitor's session, never create one.
        $sessionId = $agent !== null ? $chatSession->find($agent) : null;

        if ($agent === null || $sessionId === null) {
            return [];
        }

        $session = $verification->verifySessionExternalId($sessionId, $agent);

        if ($session === null) {
            return [];
        }

        // Titles are encrypted, so they are matched in PHP after decryption.
        // Only the most recent conversations are searched to bound that work.
        /** @var Collection<int, Conversation> $conversations */
        $conversations = $session->conversations()
            ->whereNull('cleared_at')
            ->where('is_visible', true)
            ->latest('id')
            ->limit(self::MAX_SEARCHED_CONVERSATIONS)
            ->get(['id', 'external_id', 'title', 'created_at']);

        if ($conversations->isEmpty()) {
            return [];
        }

        $byId = $conversations->keyBy('id');

        // Title matches — substring of the decrypted title.
        $needle = mb_strtolower($term);
        $titleMatchIds = $conversations
            ->filter(fn (Conversation $conversation): bool => str_contains(mb_strtolower((string) $conversation->title), $needle))
            ->pluck('id');

        // Content matches — BM25 over messages in these conversations, most
        // relevant first, de-duplicated to their conversations.
        $contentMatchIds = Message::query()
            ->searchContent($term)
            ->whereIn('chat_conversation_id', $conversations->pluck('id'))
            ->limit(100)
            ->pluck('chat_conversation_id')
            ->unique()
            ->values();

        return $titleMatchIds
            ->merge($contentMatchIds)
            ->unique()
            ->take(self::MAX_RESULTS)
            ->map(fn ($id): ?Conversation => $byId->get($id))
            ->filter()
            ->map(fn (Conversation $conversation): array => [
                'id' => $conversation->external_id,
                'title' => $conversation->title ?? Conversation::DEFAULT_TITLE,
                'timestamp' => $conversation->created_at?->timestamp ?? 0,
            ])
            ->values()
            ->all();
    }
}
