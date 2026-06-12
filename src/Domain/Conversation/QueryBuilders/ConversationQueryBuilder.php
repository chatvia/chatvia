<?php

namespace Domain\Conversation\QueryBuilders;

use Domain\Conversation\Models\Conversation;
use Illuminate\Database\Eloquent\Builder;

/**
 * @template TModel of Conversation
 *
 * @extends Builder<TModel>
 */
class ConversationQueryBuilder extends Builder
{
    public function forAgent(int $agentId): self
    {
        return $this->where('agent_id', $agentId);
    }

    public function withSessionAndTopic(): self
    {
        return $this->with(['session.country', 'topics']);
    }

    public function filterByTopic(?string $topicId): self
    {
        return $this->when($topicId, fn ($query) => $query->whereHas('topics', fn ($q) => $q->where('topics.id', $topicId)));
    }

    public function filterBySentiment(?string $sentiment): self
    {
        // Conversation-level sentiment is stamped by the consolidated analysis
        // pass onto `chat_conversations.sentiment` — the same column the chat-log
        // UI renders (see ChatLogConversationData). Filter on the conversation
        // row, not per-message sentiment, so the filter matches what's shown.
        return $this->when($sentiment, fn ($query) => $query->where('sentiment', $sentiment));
    }

    public function filterByTone(?string $tone): self
    {
        return $this->when($tone, fn ($query) => $query->where('tone', $tone));
    }

    public function filterByDateRange(?string $startDate, ?string $endDate): self
    {
        return $this->when($startDate && $endDate, function ($query) use ($startDate, $endDate): void {
            $query->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59']);
        });
    }

    public function filterByCountry(?string $countryId): self
    {
        return $this->when($countryId, function ($query) use ($countryId): void {
            $query->whereHas('session', fn ($q) => $q->where('country_id', $countryId));
        });
    }

    public function filterByConfidenceScore(?string $confidenceScore): self
    {
        return $this->when($confidenceScore, function ($query) use ($confidenceScore): void {
            $query->whereHas('messages', function ($q) use ($confidenceScore): void {
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
        return $this->when($feedback, function ($query) use ($feedback): void {
            $query->whereHas('messages', function ($q) use ($feedback): void {
                $q->where('role', 'assistant')
                    ->whereNotNull('feedback')
                    ->where('feedback', $feedback);
            });
        });
    }

    public function filterBySource(?string $source): self
    {
        // Each conversation carries its originating surface on the
        // `chat_conversations.source` enum (hosted_chat / widget / bar). A
        // simple equality filter — no message-level whereHas needed.
        return $this->when($source, fn ($query) => $query->where('source', $source));
    }

    public function filterByOnlineStatus(?bool $onlineOnly): self
    {
        return $this->when($onlineOnly, function ($query): void {
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
        return $this->where('is_visible', true);
    }
}
