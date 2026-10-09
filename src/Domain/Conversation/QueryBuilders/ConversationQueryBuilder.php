<?php

namespace Domain\Conversation\QueryBuilders;

use Domain\Conversation\Enums\ConfidenceBand;
use Domain\Conversation\Enums\MessageRole;
use Domain\Conversation\Models\Conversation;
use Illuminate\Database\Eloquent\Builder;

/**
 * Query scopes for conversations: the chat-log filters and the lookups that
 * keep a conversation inside the session it belongs to.
 *
 * @template TModel of Conversation
 *
 * @extends Builder<TModel>
 */
class ConversationQueryBuilder extends Builder
{
    /** A visitor counts as online while their conversation was active this recently. */
    public const int ONLINE_WINDOW_MINUTES = 3;

    /** @return self<TModel> */
    public function forAgent(int $agentId): self
    {
        return $this->where('agent_id', $agentId);
    }

    /**
     * Only conversations of one visitor session of one agent. Lookups by a
     * client-supplied conversation id go through this, so an id from another
     * session or agent never matches.
     *
     * @return self<TModel>
     */
    public function inSession(string $sessionExternalId, int $agentId): self
    {
        return $this->whereHas('session', static function (Builder $query) use ($sessionExternalId, $agentId): void {
            $query->where('external_id', $sessionExternalId)->where('agent_id', $agentId);
        });
    }

    /** @return self<TModel> */
    public function withSessionAndTopic(): self
    {
        return $this->with(['session.country', 'topics']);
    }

    /** @return self<TModel> */
    public function filterByTopic(?string $topicId): self
    {
        return $this->when($topicId, fn ($query) => $query->whereHas('topics', fn ($q) => $q->where('topics.id', $topicId)));
    }

    /** @return self<TModel> */
    public function filterBySentiment(?string $sentiment): self
    {
        // Conversation-level sentiment is stamped by the consolidated analysis
        // pass onto `chat_conversations.sentiment` — the same column the chat-log
        // UI renders (see ChatLogConversationData). Filter on the conversation
        // row, not per-message sentiment, so the filter matches what's shown.
        return $this->when($sentiment, fn ($query) => $query->where('sentiment', $sentiment));
    }

    /** @return self<TModel> */
    public function filterByTone(?string $tone): self
    {
        return $this->when($tone, fn ($query) => $query->where('tone', $tone));
    }

    /** @return self<TModel> */
    public function filterByDateRange(?string $startDate, ?string $endDate): self
    {
        return $this->when($startDate && $endDate, function ($query) use ($startDate, $endDate): void {
            $query->whereBetween('created_at', [$startDate.' 00:00:00', $endDate.' 23:59:59']);
        });
    }

    /** @return self<TModel> */
    public function filterByCountry(?string $countryId): self
    {
        return $this->when($countryId, function ($query) use ($countryId): void {
            $query->whereHas('session', fn ($q) => $q->where('country_id', $countryId));
        });
    }

    /** @return self<TModel> */
    public function filterByConfidenceScore(?string $confidenceScore): self
    {
        return $this->when($confidenceScore, function ($query) use ($confidenceScore): void {
            $query->whereHas('messages', function ($q) use ($confidenceScore): void {
                $q->where('role', MessageRole::Assistant);
                match (ConfidenceBand::tryFrom($confidenceScore)) {
                    ConfidenceBand::High => $q->where('confidence_score', '>=', ConfidenceBand::HIGH_FLOOR),
                    ConfidenceBand::Medium => $q->whereBetween('confidence_score', [ConfidenceBand::MEDIUM_FLOOR, ConfidenceBand::HIGH_FLOOR]),
                    ConfidenceBand::Low => $q->where('confidence_score', '<', ConfidenceBand::MEDIUM_FLOOR),
                    null => null,
                };
            });
        });
    }

    /** @return self<TModel> */
    public function filterByFeedback(?string $feedback): self
    {
        return $this->when($feedback, function ($query) use ($feedback): void {
            $query->whereHas('messages', function ($q) use ($feedback): void {
                $q->where('role', MessageRole::Assistant)
                    ->whereNotNull('feedback')
                    ->where('feedback', $feedback);
            });
        });
    }

    /** @return self<TModel> */
    public function filterBySource(?string $source): self
    {
        // Each conversation carries its originating surface on the
        // `chat_conversations.source` enum (hosted_chat / widget / bar). A
        // simple equality filter — no message-level whereHas needed.
        return $this->when($source, fn ($query) => $query->where('source', $source));
    }

    /** @return self<TModel> */
    public function filterByOnlineStatus(?bool $onlineOnly): self
    {
        return $this->when($onlineOnly, function ($query): void {
            $query->where('last_activity_at', '>=', now()->subMinutes(self::ONLINE_WINDOW_MINUTES));
        });
    }

    /** @return self<TModel> */
    public function orderByLatestActivity(): self
    {
        return $this->orderByDesc('updated_at');
    }

    /** @return self<TModel> */
    public function onlyVisible(): self
    {
        return $this->where('is_visible', true);
    }
}
