<?php

namespace Domain\Integration\Mcp\Services\Tools\Analytics;

use Domain\Analytics\Services\Chart\AnalyticsChart;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description(<<<'TXT'
Token consumption for a period: total tokens used, the prompt vs completion
split, the average tokens per AI reply, and a per-day series. Answers "how many
tokens did I consume this cycle?", "what's my prompt vs completion split?".
Tokens are reported for analytics only — billing is per message received, not
per token.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class GetTokenUsageTool extends AnalyticsTool
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

        // Tokens live on each message_sent event (exact occurred_at) — never the
        // hour-bucketed rollup, which drops a mid-hour cycle's first partial hour.
        $events = fn () => $this->analytics()->table('analytics_events')
            ->where('workspace_id', $workspace->id)
            ->where('event_type', 'message_sent')
            ->whereBetween('occurred_at', [$period->start, $period->end])
            ->when($scope['agent'], fn ($q) => $q->where('agent_id', $scope['agent']->id));

        $totals = $events()
            ->selectRaw("COALESCE(SUM(total_tokens),0) total, COALESCE(SUM(prompt_tokens),0) prompt, COALESCE(SUM(completion_tokens),0) completion, COUNT(*) FILTER (WHERE role = 'assistant' AND total_tokens > 0) replies")
            ->first();

        $total = (int) $totals->total;
        $prompt = (int) $totals->prompt;
        $completion = (int) $totals->completion;
        $replies = (int) $totals->replies;
        $avgPerReply = $replies > 0 ? (int) round($total / $replies) : 0;

        $daily = $events()
            ->selectRaw('occurred_at::date date, SUM(total_tokens) tokens')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(fn ($r): array => ['date' => (string) $r->date, 'tokens' => (int) $r->tokens])
            ->all();

        $summary = $total === 0
            ? sprintf('No tokens consumed %s.', $period->label)
            : sprintf(
                '%s: %s tokens consumed (%s prompt, %s completion), ~%s per AI reply.',
                ucfirst($period->label),
                number_format($total),
                number_format($prompt),
                number_format($completion),
                number_format($avgPerReply),
            );

        $structured = [
            'period' => ['key' => $period->key, 'label' => $period->label, 'start' => $period->start->toDateString(), 'end' => $period->end->toDateString()],
            'total_tokens' => $total,
            'prompt_tokens' => $prompt,
            'completion_tokens' => $completion,
            'avg_tokens_per_reply' => $avgPerReply,
            'daily' => $daily,
        ];

        if (count($daily) > 1) {
            $structured['echarts_option'] = AnalyticsChart::line(
                array_column($daily, 'date'),
                [['name' => 'Tokens', 'data' => array_column($daily, 'tokens')]],
                'Tokens per day — '.$period->label,
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
