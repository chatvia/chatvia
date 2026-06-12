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
Where visitors come from and what they use, for a period: the top countries by
conversations, plus a breakdown of device types, browsers, and operating
systems. Answers "top countries?", "what devices do people chat from?", "mostly
mobile or desktop?". Country counts work well as a map or bar chart.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class GetGeographyStatsTool extends AnalyticsTool
{
    public function handle(Request $request): Response|ResponseFactory
    {
        $v = $request->validate([
            ...$this->commonRules(),
            'limit' => ['nullable', 'integer', 'min:1', 'max:50'],
        ]);

        $workspace = $this->workspace();
        if (! $workspace) {
            return Response::error('No workspace context for this analytics request.');
        }

        $period = $this->resolvePeriod($workspace, $v);
        $scope = $this->agentScope($workspace, $v);
        $limit = (int) ($v['limit'] ?? 10);
        $sentinel = config('chatvia.analytics.unknown_country_code', '--');

        $countryRows = $this->analytics()->table('analytics_agent_hourly_rollups')
            ->where('workspace_id', $workspace->id)
            ->where('country_code', '!=', $sentinel)
            ->whereBetween('bucket_hour', [$period->start->copy()->startOfHour(), $period->end])
            ->when($scope['agent'], fn ($q) => $q->where('agent_id', $scope['agent']->id))
            ->groupBy('country_code')
            ->selectRaw('country_code, SUM(conversations_started) conversations')
            ->havingRaw('SUM(conversations_started) > 0')
            ->orderByRaw('SUM(conversations_started) DESC')
            ->limit($limit)
            ->get();

        $names = DB::table('countries')
            ->whereIn('code', $countryRows->pluck('country_code')->map(fn ($c) => strtolower((string) $c))->all())
            ->pluck('name', 'code');

        $countryTotal = (int) $countryRows->sum('conversations');
        $countries = $countryRows->map(fn ($r): array => [
            'code' => strtoupper((string) $r->country_code),
            'name' => $names[strtolower((string) $r->country_code)] ?? strtoupper((string) $r->country_code),
            'conversations' => (int) $r->conversations,
            'pct' => $this->pct((int) $r->conversations, $countryTotal),
        ])->all();

        $breakdown = fn (string $column) => DB::table('chat_sessions')
            ->whereIn('agent_id', $scope['ids'])
            ->whereBetween('created_at', [$period->start, $period->end])
            ->whereNotNull($column)
            ->groupBy($column)
            ->selectRaw("$column as label, COUNT(*) total")
            ->orderByDesc('total')
            ->limit(8)
            ->get()
            ->map(fn ($r): array => ['label' => (string) $r->label, 'count' => (int) $r->total])
            ->all();

        $summary = empty($countries)
            ? sprintf('No location data %s yet.', $period->label)
            : sprintf(
                'Top country %s is %s (%s conversations, %s%%).',
                $period->label,
                $countries[0]['name'],
                number_format($countries[0]['conversations']),
                $countries[0]['pct'],
            );

        $structured = [
            'period' => ['key' => $period->key, 'label' => $period->label, 'start' => $period->start->toDateString(), 'end' => $period->end->toDateString()],
            'countries' => $countries,
            'devices' => $breakdown('device_type'),
            'browsers' => $breakdown('browser'),
            'operating_systems' => $breakdown('os'),
        ];

        if (count($countries) > 1) {
            $structured['echarts_option'] = AnalyticsChart::bar(
                array_column($countries, 'name'),
                [['name' => 'Conversations', 'data' => array_column($countries, 'conversations')]],
                'Conversations by country — '.$period->label,
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
        return [
            ...$this->commonSchema($schema),
            'limit' => $schema->integer()
                ->description('How many top countries to return (1–50). Defaults to 10.')
                ->default(10),
        ];
    }
}
