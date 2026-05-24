<?php

namespace Domain\Chat\QueryBuilders;

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

    public function filterByTone(?string $tone): self
    {
        return $this->when($tone, function ($query) use ($tone) {
            $query->whereHas('messages', fn ($q) => $q->where('tone', $tone));
        });
    }

    public function filterByDateRange(?string $startDate, ?string $endDate): self
    {
        return $this->when($startDate && $endDate, function ($query) use ($startDate, $endDate) {
            $query->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59']);
        });
    }

    public function filterByCountry(?string $countryId): self
    {
        return $this->when($countryId, function ($query) use ($countryId) {
            $query->whereHas('session', fn ($q) => $q->where('country_id', $countryId));
        });
    }

    public function filterByConfidenceScore(?string $confidenceScore): self
    {
        return $this->when($confidenceScore, function ($query) use ($confidenceScore) {
            $query->whereHas('messages', function ($q) use ($confidenceScore) {
                $q->where('role', 'assistant');
                match ($confidenceScore) {
                    'high' => $q->where('confidence_score', '>=', 0.8),
                    'medium' => $q->whereBetween('confidence_score', [0.65, 0.8]),
                    'low' => $q->where('confidence_score', '<', 0.65),
                    default => null,
                };
            });
        });
    }

    public function filterByFeedback(?string $feedback): self
    {
        return $this->when($feedback, function ($query) use ($feedback) {
            $query->whereHas('messages', function ($q) use ($feedback) {
                $q->where('role', 'assistant')
                    ->whereNotNull('feedback')
                    ->where('feedback', $feedback);
            });
        });
    }

    public function filterBySource(?string $source): self
    {
        return $this->when($source, function ($query) use ($source) {
            match ($source) {
                'widget' => $query->whereHas('messages', fn ($q) => $q->where('source', 'widget')),
                'playground' => $query->whereHas('messages', fn ($q) => $q->where('source', 'playground')),
                'api' => $query->whereHas('messages', fn ($q) => $q->where('source', 'api')),
                default => null,
            };
        });
    }

    public function filterByOnlineStatus(?bool $onlineOnly): self
    {
        return $this->when($onlineOnly, function ($query) {
            // Consider users "online" if active within the last 3 minutes
            $query->where('last_activity_at', '>=', now()->subMinutes(3));
        });
    }

    public function orderByLatestActivity(): self
    {
        return $this->orderByDesc('updated_at');
    }

    public function onlyVisible(): self
    {
        return $this->where('visible', true);
    }
}
