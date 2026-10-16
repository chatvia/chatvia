<?php

declare(strict_types=1);

namespace Domain\Conversation\Queries;

use Domain\Conversation\Data\Conversations\ConversationSearchHitData;
use Domain\Conversation\Models\Conversation;
use Domain\Conversation\Models\Message;
use Domain\Conversation\Models\Session;
use Illuminate\Support\Collection;

/**
 * Search one chat session's conversations for the sidebar palette. Matches on
 * both the conversation title (encrypted at rest, so compared in memory after
 * the model decrypts it) and the message content (a word match ranked by
 * full-text rank — see MessageQueryBuilder::searchContent). Title hits lead,
 * then content hits.
 */
class SearchSessionConversationsQuery
{
    private const int MAX_RESULTS = 50;

    private const int MAX_SEARCHED_CONVERSATIONS = 1000;

    /**
     * @return Collection<int, ConversationSearchHitData>
     */
    public function forSession(Session $session, string $term): Collection
    {
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
            return collect();
        }

        $byId = $conversations->keyBy('id');

        // Title matches — substring of the decrypted title.
        $needle = mb_strtolower($term);
        $titleMatchIds = $conversations
            ->filter(fn (Conversation $conversation): bool => str_contains(mb_strtolower((string) $conversation->title), $needle))
            ->pluck('id');

        // Content matches — full-text search over messages in these
        // conversations, most relevant first, de-duplicated to their conversations.
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
            ->map(fn (Conversation $conversation): ConversationSearchHitData => ConversationSearchHitData::fromModel($conversation))
            ->values();
    }
}
