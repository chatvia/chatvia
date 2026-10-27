<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Analytics;

use Domain\Analytics\Data\Account\AnalyticsScopeData;
use Domain\Analytics\Queries\Account\Activity\ResponseTimeStatsQuery;
use Illuminate\Support\Number;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * Analytics MCP tool `get-response-time-stats`: how fast the AI replies in a
 * period (average, p50, p95 and slowest reply, plus the average time to the
 * first reply). Returns a one-line summary for the model and the full figures
 * as structured content.
 */
#[Description(<<<'TXT'
How fast the AI responds, for a period: the average reply time, the median (p50)
and 95th-percentile (p95), the slowest reply, and the average time to the first
reply in a conversation. All in milliseconds (first-response in seconds).
Answers "how fast are replies?", "what's my p95 latency?", "how long until the
first answer?".
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class GetResponseTimeStatsTool extends AnalyticsTool
{
    public function __construct(private readonly ResponseTimeStatsQuery $responseTimes) {}

    protected function answer(AnalyticsScopeData $scope, array $input): Response|ResponseFactory
    {
        $data = $this->responseTimes->get($scope);
        $period = $scope->period;

        // The average comes from the rollup; p95 and the slowest reply need
        // the raw events, which a window older than their retention lacks.
        $summary = match (true) {
            $data->avgMs === null => sprintf('No response-time data %s yet.', $period->label),
            $data->p95Ms === null => sprintf('Avg reply %s: %sms.', $period->label, Number::format(round((float) $data->avgMs))),
            default => sprintf(
                'Avg reply %s: %sms (p95 %sms, slowest %sms)%s.',
                $period->label,
                Number::format(round((float) $data->avgMs)),
                Number::format(round((float) $data->p95Ms)),
                Number::format((int) $data->maxMs),
                $data->avgFirstResponseSeconds !== null ? ', first reply ~'.$data->avgFirstResponseSeconds.'s' : '',
            ),
        };

        return Response::make(Response::text($summary))->withStructuredContent([
            'period' => $this->periodPayload($period),
            'replies_measured' => $data->repliesMeasured,
            'avg_response_ms' => $data->avgMs,
            'p50_response_ms' => $data->p50Ms,
            'p95_response_ms' => $data->p95Ms,
            'max_response_ms' => $data->maxMs,
            'avg_first_response_seconds' => $data->avgFirstResponseSeconds,
        ]);
    }
}
