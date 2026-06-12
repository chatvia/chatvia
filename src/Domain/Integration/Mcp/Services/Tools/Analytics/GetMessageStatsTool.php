<?php

namespace Domain\Integration\Mcp\Services\Tools\Analytics;

use Domain\Analytics\Services\Chart\AnalyticsChart;
use Domain\Analytics\Services\Period;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description(<<<'TXT'
Message volume for a period: how many messages came in (from visitors) vs went
out (AI replies), the combined total, the change vs the previous period, the
average messages per conversation, and a per-day series. Answers "how many
messages this cycle?", "incoming vs outgoing?", "are we up or down vs last
cycle?". The per-day series is ideal to hand to a chart tool.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class GetMessageStatsTool extends AnalyticsTool
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

        // Count from the granular event log (exact occurred_at) rather than the
        // hour-bucketed rollup: a billing cycle that starts mid-hour (e.g.
        // 17:10) would otherwise drop its first partial hour, because the
        // rollup's bucket_hour is truncated to 17:00 and falls before the start.
        $totals = fn (Period $p) => $this->analytics()->table('analytics_events')
            ->where('workspace_id', $workspace->id)
            ->where('event_type', 'message_sent')
            ->whereBetween('occurred_at', [$p->start, $p->end])
            ->when($scope['agent'], fn ($q) => $q->where('agent_id', $scope['agent']->id))
            ->selectRaw("COUNT(*) FILTER (WHERE role = 'user') incoming, COUNT(*) FILTER (WHERE role = 'assistant') outgoing")
            ->first();

        $current = $totals($period);
        $previous = $totals($period->previousWindow());

        $incoming = (int) $current->incoming;
        $outgoing = (int) $current->outgoing;
        $total = $incoming + $outgoing;
        $previousTotal = (int) $previous->incoming + (int) $previous->outgoing;
        $changePct = $previousTotal > 0 ? round((($total - $previousTotal) / $previousTotal) * 100, 1) : null;

        $avgPerConversation = $this->round($this->analytics()->table('analytics_conversation_metrics')
            ->where('workspace_id', $workspace->id)
            ->whereBetween('started_at', [$period->start, $period->end])
            ->when($scope['agent'], fn ($q) => $q->where('agent_id', $scope['agent']->id))
            ->avg('message_count'), 1);

        $daily = $this->analytics()->table('analytics_events')
            ->where('workspace_id', $workspace->id)
            ->where('event_type', 'message_sent')
            ->whereBetween('occurred_at', [$period->start, $period->end])
            ->when($scope['agent'], fn ($q) => $q->where('agent_id', $scope['agent']->id))
            ->selectRaw('occurred_at::date date, COUNT(*) messages')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($r): array => ['date' => (string) $r->date, 'messages' => (int) $r->messages])
            ->all();

        $trend = $changePct === null ? '' : sprintf(' %s %s%% vs %s.', $changePct >= 0 ? 'up' : 'down', abs($changePct), 'the prior period');

        $summary = sprintf(
            '%s: %s messages total — %s incoming, %s AI replies.%s',
            ucfirst($period->label),
            number_format($total),
            number_format($incoming),
            number_format($outgoing),
            $trend,
        );

        $structured = [
            'period' => ['key' => $period->key, 'label' => $period->label, 'start' => $period->start->toDateString(), 'end' => $period->end->toDateString()],
            'incoming_messages' => $incoming,
            'ai_messages' => $outgoing,
            'total_messages' => $total,
            'previous_total' => $previousTotal,
            'change_pct' => $changePct,
            'avg_messages_per_conversation' => $avgPerConversation,
            'daily' => $daily,
        ];

        // Auto-render a trend line so the owner sees the shape without asking.
        if (count($daily) > 1) {
            $structured['echarts_option'] = AnalyticsChart::line(
                array_column($daily, 'date'),
                [['name' => 'Messages', 'data' => array_column($daily, 'messages')]],
                'Messages per day — '.$period->label,
            );
        }

        return Response::make(Response::text($summary))->withStructuredContent($structured);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        return $this->commonSchema($schema);
    }
}
