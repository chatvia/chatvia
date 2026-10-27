<?php

namespace Domain\Conversation\QueryBuilders;

use Domain\Conversation\Models\Message;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Query scopes for chat-log messages: reading one conversation safely inside
 * its agent, full-text search over message content, and the first-message
 * excerpts shown in conversation lists.
 *
 * @template TModel of Message
 *
 * @extends Builder<TModel>
 */
class MessageQueryBuilder extends Builder
{
    /**
     * Messages of one conversation, only when that conversation belongs to the
     * agent. The agent check is part of the query on purpose: a caller that
     * forgets to verify ownership first still gets no rows from another
     * agent's (or tenant's) conversation. The EXISTS probe is a primary-key
     * lookup, so it costs next to nothing.
     *
     * @return self<TModel>
     */
    public function forAgentConversation(int $agentId, int $conversationId): self
    {
        return $this->where('chat_messages.chat_conversation_id', $conversationId)
            ->whereExists(function ($query) use ($agentId): void {
                $query->selectRaw('1')
                    ->from('chat_conversations')
                    ->whereColumn('chat_conversations.id', 'chat_messages.chat_conversation_id')
                    ->where('chat_conversations.agent_id', $agentId);
            });
    }

    /**
     * Restrict to messages whose content matches $term, most relevant first.
     *
     * Matching is a word-level filter on the plaintext content (any word of
     * the term, case-insensitive). The matches are ranked by Postgres
     * full-text rank over the words, computed for the matched rows only: the
     * search always runs inside one visitor's own conversations, so a stored
     * search vector (and its write cost on every message) is not needed.
     *
     * Scope (conversation / agent / workspace) and ->limit() at the call site.
     *
     * @return self<TModel>
     */
    public function searchContent(string $term): self
    {
        $words = Str::of($term)
            ->trim()
            ->split('/\s+/', -1, PREG_SPLIT_NO_EMPTY)
            ->filter(fn (string $word): bool => Str::length($word) >= 2)
            ->values();

        // No searchable token (blank or all single chars) → match nothing.
        if ($words->isEmpty()) {
            return $this->whereRaw('1 = 0');
        }

        // 'simple' keeps every language's words as they are (no stemming);
        // the web-search syntax never fails on stray quotes or operators.
        $rank = "ts_rank(to_tsvector('simple', coalesce(content, '')), websearch_to_tsquery('simple', ?)) DESC";
        $query = $words->implode(' or ');

        return $this
            ->where(function (Builder $query) use ($words): void {
                foreach ($words as $word) {
                    $query->orWhere('content', 'ILIKE', "%{$this->escapeLike($word)}%");
                }
            })
            ->orderByRaw($rank, [$query])
            ->orderByDesc('created_at');
    }

    /** Escape LIKE/ILIKE wildcards so user input is matched literally. */
    private function escapeLike(string $value): string
    {
        return Str::replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * First message excerpt per conversation, in one query: for each id, a
     * LATERAL subquery reads the first message only (a forward scan of the
     * (chat_conversation_id, created_at, id) index, one row per
     * conversation). Returns a Collection keyed by chat_conversation_id with
     * the trimmed plain-text excerpt as the value; a conversation without
     * messages is left out.
     *
     * @param  iterable<int>  $conversationIds
     * @return Collection<int, string>
     */
    public function firstExcerptsForConversations(iterable $conversationIds, int $excerptLength = 80): Collection
    {
        $ids = collect($conversationIds)->filter()->map(static fn ($id): int => (int) $id)->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        $query = $this->getQuery()->newQuery();

        $first = $query->newQuery()
            ->from('chat_messages')
            ->select('chat_messages.content')
            ->whereColumn('chat_messages.chat_conversation_id', 'conversation.id')
            ->orderBy('chat_messages.created_at')
            ->orderBy('chat_messages.id')
            ->limit(1);

        return $query
            ->fromRaw('unnest(?::bigint[]) as conversation(id)', ['{'.$ids->implode(',').'}'])
            ->joinLateral($first, 'first_message')
            ->get(['conversation.id', 'first_message.content'])
            ->mapWithKeys(static fn (object $row): array => [
                (int) $row->id => str($row->content ?? '')->stripTags()->limit($excerptLength)->value(),
            ]);
    }
}
