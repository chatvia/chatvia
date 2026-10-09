<?php

namespace Domain\Conversation\QueryBuilders;

use Domain\Conversation\Models\Message;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

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
     * Restrict to messages whose content matches $term, ranked by BM25 relevance.
     *
     * Matching is a word-level lexical filter on the plaintext content:
     * the llmlingua2 tokenizer emits shared subword units, so the bm25 operator
     * alone scores unrelated text weakly-negative (noise) and can't tell a real
     * hit from none. Ranking, however, is BM25 over content_bm25 — `<&>` returns
     * the negative score, so ascending order is most-relevant-first, tokenized
     * with the same llmlingua2 model used at write time (see Message::booted).
     *
     * Scope (conversation / agent / workspace) and ->limit() at the call site.
     *
     * @return self<TModel>
     */
    public function searchContent(string $term): self
    {
        $words = collect(preg_split('/\s+/', trim($term), -1, PREG_SPLIT_NO_EMPTY) ?: [])
            ->filter(fn (string $word): bool => mb_strlen($word) >= 2)
            ->values();

        // No searchable token (blank or all single chars) → match nothing.
        if ($words->isEmpty()) {
            return $this->whereRaw('1 = 0');
        }

        $bm25 = "content_bm25 <&> bm25_catalog.to_bm25query('chat_messages_content_bm25', tokenizer_catalog.tokenize(?, 'llmlingua2'))";

        return $this
            ->where(function (Builder $query) use ($words): void {
                foreach ($words as $word) {
                    $query->orWhere('content', 'ILIKE', '%'.$this->escapeLike($word).'%');
                }
            })
            ->orderByRaw($bm25, [$term]);
    }

    /** Escape LIKE/ILIKE wildcards so user input is matched literally. */
    private function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    /**
     * First message excerpt per conversation, in one DISTINCT ON query.
     * Returns a Collection keyed by chat_conversation_id with the trimmed
     * plain-text excerpt as the value.
     *
     * @param  iterable<int>  $conversationIds
     * @return Collection<int, string>
     */
    public function firstExcerptsForConversations(iterable $conversationIds, int $excerptLength = 80): Collection
    {
        $ids = collect($conversationIds)->filter()->unique()->values();

        if ($ids->isEmpty()) {
            return collect();
        }

        // Hydrate via Eloquent so the excerpt goes through the model (stripTags
        // below). DISTINCT ON + ORDER BY compile identically on the Eloquent
        // builder under Postgres.
        $rows = Message::query()
            ->select(['chat_conversation_id', 'content'])
            ->whereIn('chat_conversation_id', $ids)
            ->orderBy('chat_conversation_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->distinct('chat_conversation_id')
            ->get();

        return $rows->mapWithKeys(fn (Message $row): array => [
            $row->chat_conversation_id => str($row->content ?? '')->stripTags()->limit($excerptLength)->value(),
        ]);
    }
}
