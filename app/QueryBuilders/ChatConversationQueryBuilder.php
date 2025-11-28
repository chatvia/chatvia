<?php

namespace App\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;

class ChatConversationQueryBuilder extends Builder
{
    public function forAgent(string $agentId): self
    {
        return $this->whereHas('session', fn ($query) => $query->where('agent_id', $agentId));
    }

    public function withSessionAndTopic(): self
    {
        return $this->with(['session.country', 'topic']);
    }

    public function filterByTopic(?string $topicId): self
    {
        return $this->when($topicId, fn ($query) => $query->where('chat_topic_id', $topicId));
    }

    public function filterBySentiment(?string $sentiment): self
    {
        return $this->when($sentiment, function ($query) use ($sentiment) {
            $query->whereHas('messages', fn ($q) => $q->where('sentiment', $sentiment));
        });
    }

    public function filterByEmotion(?string $emotion): self
    {
        return $this->when($emotion, function ($query) use ($emotion) {
            $query->whereHas('messages', fn ($q) => $q->where('emotion', $emotion));
        });
    }

    public function orderByLatestActivity(): self
    {
        return $this->orderByDesc('updated_at');
    }
}
