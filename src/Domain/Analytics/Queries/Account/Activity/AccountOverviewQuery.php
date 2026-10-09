<?php

namespace Domain\Analytics\Queries\Account\Activity;

use Domain\Analytics\Data\Account\Activity\AccountOverviewData;
use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Queries\Account\AccountAnalyticsQuery;

/**
 * The one-shot account snapshot: messages, conversations, visitors, leads,
 * reply time, feedback, tokens and this cycle's message usage.
 */
class AccountOverviewQuery extends AccountAnalyticsQuery
{
    public function get(AnalyticsScopeData $scope): AccountOverviewData
    {
        $messages = $this->messageEvents($scope)
            ->selectRaw("COUNT(*) FILTER (WHERE role = 'user') user_messages, COUNT(*) FILTER (WHERE role = 'assistant') assistant_messages, COALESCE(SUM(total_tokens),0) tokens, CASE WHEN COUNT(*) FILTER (WHERE response_time_ms IS NOT NULL) > 0 THEN AVG(response_time_ms) END avg_response_ms")
            ->first();

        $credit = $scope->workspace->credit;
        $messagesUsed = $credit?->usage?->messages;
        $messagesLimit = $credit?->messages;

        return new AccountOverviewData(
            incomingMessages: (int) $messages->user_messages,
            aiMessages: (int) $messages->assistant_messages,
            conversations: $this->conversationMetrics($scope)->count(),
            uniqueVisitors: $this->sessionFirsts($scope)->count(),
            leads: $this->leadCount($scope),
            avgResponseMs: $this->roundOrNull($messages->avg_response_ms, 0),
            feedback: $this->feedback($scope),
            tokens: (int) $messages->tokens,
            messagesUsedThisCycle: $messagesUsed !== null ? (int) $messagesUsed : null,
            messagesLimitThisCycle: $messagesLimit !== null ? (int) $messagesLimit : null,
        );
    }
}
