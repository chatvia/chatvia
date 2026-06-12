<?php

namespace Domain\Integration\Mcp\Services\Tools\Analytics;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

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
    public function handle(Request $request): Response|ResponseFactory
    {
        $v = $request->validate($this->commonRules());

        $workspace = $this->workspace();
        if (! $workspace) {
            return Response::error('No workspace context for this analytics request.');
        }

        $period = $this->resolvePeriod($workspace, $v);
        $scope = $this->agentScope($workspace, $v);

        $dist = $this->analytics()->table('analytics_events')
            ->where('workspace_id', $workspace->id)
            ->where('event_type', 'message_sent')
            ->where('role', 'assistant')
            ->whereNotNull('response_time_ms')
            ->whereBetween('occurred_at', [$period->start, $period->end])
            ->when($scope['agent'], fn ($q) => $q->where('agent_id', $scope['agent']->id))
            ->selectRaw('AVG(response_time_ms) avg_ms, PERCENTILE_CONT(0.5) WITHIN GROUP (ORDER BY response_time_ms) p50_ms, PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY response_time_ms) p95_ms, MAX(response_time_ms) max_ms, COUNT(*) n')
            ->first();

        $first = $this->analytics()->selectOne(
            'SELECT AVG(EXTRACT(EPOCH FROM (a.occurred_at - u.occurred_at))) avg_first
             FROM (SELECT conversation_external_id, MIN(occurred_at) occurred_at FROM analytics_events
                   WHERE workspace_id = ? AND event_type = ? AND role = ? AND occurred_at BETWEEN ? AND ?
                   GROUP BY conversation_external_id) u
             JOIN (SELECT conversation_external_id, MIN(occurred_at) occurred_at FROM analytics_events
                   WHERE workspace_id = ? AND event_type = ? AND role = ? AND occurred_at BETWEEN ? AND ?
                   GROUP BY conversation_external_id) a ON a.conversation_external_id = u.conversation_external_id
             WHERE a.occurred_at > u.occurred_at',
            [
                $workspace->id, 'message_sent', 'user', $period->start->toDateTimeString(), $period->end->toDateTimeString(),
                $workspace->id, 'message_sent', 'assistant', $period->start->toDateTimeString(), $period->end->toDateTimeString(),
            ],
        );

        $avgMs = $this->round($dist->avg_ms, 0);
        $avgFirst = $this->round($first->avg_first ?? null, 1);

        $summary = $avgMs === null
            ? sprintf('No response-time data %s yet.', $period->label)
            : sprintf(
                'Avg reply %s: %sms (p95 %sms, slowest %sms)%s.',
                $period->label,
                number_format((float) $avgMs),
                number_format((float) $this->round($dist->p95_ms, 0)),
                number_format((int) $dist->max_ms),
                $avgFirst !== null ? ', first reply ~'.$avgFirst.'s' : '',
            );

        return Response::make(Response::text($summary))->withStructuredContent([
            'period' => ['key' => $period->key, 'label' => $period->label, 'start' => $period->start->toDateString(), 'end' => $period->end->toDateString()],
            'replies_measured' => (int) $dist->n,
            'avg_response_ms' => $avgMs,
            'p50_response_ms' => $this->round($dist->p50_ms, 0),
            'p95_response_ms' => $this->round($dist->p95_ms, 0),
            'max_response_ms' => $dist->max_ms !== null ? (int) $dist->max_ms : null,
            'avg_first_response_seconds' => $avgFirst,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->commonSchema($schema);
    }
}
