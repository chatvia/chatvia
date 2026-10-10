<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Analytics;

use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Queries\Account\Activity\AccountOverviewQuery;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * get-account-overview: the one-shot account snapshot.
 */
#[Description(<<<'TXT'
A one-shot snapshot of the whole account for a period: total messages (incoming
+ AI replies), conversations, unique visitors, leads captured, average response
time, thumbs up/down, tokens, and — when the account has a monthly message
limit — this month's messages used vs that limit. Use this first when the user asks "how's my account doing?" or wants
an overview, then drill in with the more specific tools.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class GetAccountOverviewTool extends AnalyticsTool
{
    public function __construct(private readonly AccountOverviewQuery $overview) {}

    protected function answer(AnalyticsScopeData $scope, array $input): Response|ResponseFactory
    {
        $data = $this->overview->get($scope);
        $feedback = $data->feedback;

        $summary = sprintf(
            '%s: %s messages (%s in, %s AI) across %s conversations from %s visitors, %s leads captured.%s%s',
            ucfirst($scope->period->label),
            number_format($data->totalMessages()),
            number_format($data->incomingMessages),
            number_format($data->aiMessages),
            number_format($data->conversations),
            number_format($data->uniqueVisitors),
            number_format($data->leads),
            $data->avgResponseMs !== null ? ' Avg response '.number_format((float) $data->avgResponseMs).'ms.' : '',
            $feedback->rated() > 0 ? ' Feedback: '.$feedback->positive.'👍 / '.$feedback->negative.'👎.' : '',
        );

        return Response::make(Response::text($summary))->withStructuredContent([
            'period' => $this->periodPayload($scope->period),
            'incoming_messages' => $data->incomingMessages,
            'ai_messages' => $data->aiMessages,
            'total_messages' => $data->totalMessages(),
            'conversations' => $data->conversations,
            'unique_visitors' => $data->uniqueVisitors,
            'leads' => $data->leads,
            'avg_response_ms' => $data->avgResponseMs,
            'positive_feedback' => $feedback->positive,
            'negative_feedback' => $feedback->negative,
            'tokens' => $data->tokens,
            'messages_used_this_month' => $data->messagesUsedThisMonth,
            'messages_limit_this_month' => $data->messagesLimitThisMonth,
        ]);
    }
}
