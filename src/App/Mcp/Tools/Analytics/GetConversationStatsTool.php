<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Analytics;

use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Data\Account\DailyCountData;
use Domain\Analytics\Queries\Account\Activity\ConversationStatsQuery;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * get-conversation-stats: conversation count, length, duration, busiest day.
 */
#[Description(<<<'TXT'
Conversation-level stats for a period: how many conversations started, the
average length (messages per conversation), the average duration in seconds, and
the single busiest day. Answers "how many conversations this month?", "how long
is the average chat?", "which day was busiest?".
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class GetConversationStatsTool extends AnalyticsTool
{
    public function __construct(private readonly ConversationStatsQuery $conversations) {}

    protected function answer(AnalyticsScopeData $scope, array $input): Response|ResponseFactory
    {
        $data = $this->conversations->get($scope);
        $busiest = $data->busiestDay;

        $summary = sprintf(
            '%s: %s conversations, averaging %s messages each%s.%s',
            ucfirst($scope->period->label),
            number_format($data->conversations),
            $data->avgMessagesPerConversation !== null ? number_format((float) $data->avgMessagesPerConversation, 1) : '0',
            $data->avgDurationSeconds !== null ? ' and about '.number_format((float) $data->avgDurationSeconds).'s long' : '',
            $busiest instanceof DailyCountData ? ' Busiest day was '.$busiest->date.' ('.number_format($busiest->count).' conversations).' : '',
        );

        return Response::make(Response::text($summary))->withStructuredContent([
            'period' => $this->periodPayload($scope->period),
            'conversations' => $data->conversations,
            'avg_messages_per_conversation' => $data->avgMessagesPerConversation,
            'avg_duration_seconds' => $data->avgDurationSeconds,
            'busiest_day' => $busiest instanceof DailyCountData ? ['date' => $busiest->date, 'conversations' => $busiest->count] : null,
        ]);
    }
}
