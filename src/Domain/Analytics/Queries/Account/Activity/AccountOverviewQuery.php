<?php

namespace Domain\Analytics\Queries\Account\Activity;

use Domain\Analytics\Data\Account\Activity\AccountOverviewData;
use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Queries\Account\AccountAnalyticsQuery;
use Domain\Analytics\Queries\Account\Concerns\ReadsRollups;

/**
 * The one-shot account snapshot: messages, conversations, visitors, leads,
 * reply time, feedback, tokens and this month's message usage. Messages,
 * tokens and reply time come from the hourly or daily rollup.
 */
class AccountOverviewQuery extends AccountAnalyticsQuery
{
    use ReadsRollups;

    public function get(AnalyticsScopeData $scope): AccountOverviewData
    {
        $messages = $this->rollup($scope)
            ->selectRaw('COALESCE(SUM(user_messages),0) user_messages, COALESCE(SUM(assistant_messages),0) assistant_messages, COALESCE(SUM(total_tokens),0) tokens, SUM(response_ms_sum)::numeric / NULLIF(SUM(response_ms_count),0) avg_response_ms')
            ->first();

        $limit = $scope->workspace->messageLimit;

        return new AccountOverviewData(
            incomingMessages: (int) $messages->user_messages,
            aiMessages: (int) $messages->assistant_messages,
            conversations: $this->conversationMetrics($scope)->count(),
            uniqueVisitors: $this->sessionFirsts($scope)->count(),
            leads: $this->leadCount($scope),
            avgResponseMs: $this->roundOrNull($messages->avg_response_ms, 0),
            feedback: $this->feedback($scope),
            tokens: (int) $messages->tokens,
            messagesUsedThisMonth: $limit?->usedThisMonth(),
            messagesLimitThisMonth: $limit?->monthly_limit,
        );
    }
}
