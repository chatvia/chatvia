<?php

namespace Domain\Conversation\QueryBuilders;

use Domain\Conversation\Models\Message;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;

/**
 * @template TModel of Message
 *
 * @extends Builder<TModel>
 */
class MessageQueryBuilder extends Builder
{
    public function forAgentConversation(int $agentId, int $conversationId): self
    {
        // chat_conversation_id is already authoritative; the controller verifies
        // the conversation belongs to the agent before calling this. The
        // additional whereHas would emit a redundant EXISTS subquery.
        return $this->where('chat_conversation_id', $conversationId);
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

        // Hydrate via Eloquent (not DB::table) so the model's `encrypted`
        // cast on `content` decrypts the ciphertext before we excerpt it.
        // DISTINCT ON + ORDER BY compile identically on the Eloquent builder
        // under Postgres.
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
