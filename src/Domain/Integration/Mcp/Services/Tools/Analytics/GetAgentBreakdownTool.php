<?php

namespace Domain\Integration\Mcp\Services\Tools\Analytics;

use Domain\Analytics\Services\Chart\AnalyticsChart;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Request;
use Laravel\Mcp\Response;
use Laravel\Mcp\ResponseFactory;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Description(<<<'TXT'
Per-agent activity for a period: each agent ranked by total messages and
conversations. Answers "which agent gets the most traffic?", "how is volume
split across my agents?". Only meaningful for accounts with more than one agent.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class GetAgentBreakdownTool extends AnalyticsTool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $v = $request->validate($this->commonRules());

        $workspace = $this->workspace();
        if (! $workspace) {
            return Response::error('No workspace context for this analytics request.');
        }

        $period = $this->resolvePeriod($workspace, $v);

        $rows = $this->analytics()->table('analytics_agent_hourly_rollups')
            ->where('workspace_id', $workspace->id)
            ->whereBetween('bucket_hour', [$period->start->copy()->startOfHour(), $period->end])
            ->groupBy('agent_id')
            ->selectRaw('agent_id, SUM(user_messages + assistant_messages) messages, SUM(conversations_started) conversations')
            ->orderByRaw('SUM(user_messages + assistant_messages) DESC')
            ->get();

        $names = DB::table('agents')
            ->whereIn('id', $rows->pluck('agent_id')->all())
            ->pluck('name', 'id');

        $agents = $rows->map(fn ($r): array => [
            'agent_id' => (int) $r->agent_id,
            'name' => $names[$r->agent_id] ?? 'Agent #'.$r->agent_id,
            'messages' => (int) $r->messages,
            'conversations' => (int) $r->conversations,
        ])->all();

        $summary = empty($agents)
            ? sprintf('No agent activity %s.', $period->label)
            : sprintf(
                'Busiest agent %s is %s with %s messages across %s conversations.',
                $period->label,
                $agents[0]['name'],
                number_format($agents[0]['messages']),
                number_format($agents[0]['conversations']),
            );

        $structured = [
            'period' => ['key' => $period->key, 'label' => $period->label, 'start' => $period->start->toDateString(), 'end' => $period->end->toDateString()],
            'agents' => $agents,
        ];

        if (count($agents) > 1) {
            $structured['echarts_option'] = AnalyticsChart::bar(
                array_column($agents, 'name'),
                [['name' => 'Messages', 'data' => array_column($agents, 'messages')]],
                'Messages by agent — '.$period->label,
                horizontal: true,
            );
        }

        return Response::make(Response::text($summary))->withStructuredContent($structured);
    }

    /**
     * @return array<string, mixed>
     */
    public function schema(JsonSchema $schema): array
    {
        // Whole-account only — narrowing to one agent would defeat the comparison.
        $common = $this->commonSchema($schema);

        return [
            'period' => $common['period'],
            'start_date' => $common['start_date'],
            'end_date' => $common['end_date'],
        ];
    }
}
