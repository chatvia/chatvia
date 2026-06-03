<?php

namespace Domain\Conversation\QueryBuilders;

use Domain\Conversation\Models\Message;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

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

        $rows = DB::table('chat_messages')
            ->select('chat_conversation_id', 'content')
            ->whereIn('chat_conversation_id', $ids)
            ->orderBy('chat_conversation_id')
            ->orderBy('created_at')
            ->orderBy('id')
            ->distinct('chat_conversation_id')
            ->get();

        return $rows->mapWithKeys(fn ($row): array => [
            $row->chat_conversation_id => str($row->content ?? '')->stripTags()->limit($excerptLength)->value(),
        ]);
    }
}
