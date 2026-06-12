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
When visitors are active, for a period: conversations by hour of day (0–23, UTC)
and by day of week, with the peak hour and busiest weekday called out. Answers
"when are people most active?", "what's my peak hour?", "which weekday is
busiest?". The hourly and weekday series make a good bar or heatmap chart.
TXT)]
#[IsReadOnly]
#[IsIdempotent]
class GetActivityPatternsTool extends AnalyticsTool
{
    private const DAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    public function handle(Request $request): Response|ResponseFactory
    {
        $v = $request->validate($this->commonRules());

        $workspace = $this->workspace();
        if (! $workspace) {
            return Response::error('No workspace context for this analytics request.');
        }

        $period = $this->resolvePeriod($workspace, $v);
        $scope = $this->agentScope($workspace, $v);

        $byHour = $this->analytics()->table('analytics_agent_hourly_rollups')
            ->where('workspace_id', $workspace->id)
            ->whereBetween('bucket_hour', [$period->start->copy()->startOfHour(), $period->end])
            ->when($scope['agent'], fn ($q) => $q->where('agent_id', $scope['agent']->id))
            ->selectRaw('EXTRACT(HOUR FROM bucket_hour)::int hour, SUM(conversations_started) conversations')
            ->groupBy('hour')
            ->orderBy('hour')
            ->get();

        $byDay = $this->analytics()->table('analytics_agent_hourly_rollups')
            ->where('workspace_id', $workspace->id)
            ->whereBetween('bucket_hour', [$period->start->copy()->startOfHour(), $period->end])
            ->when($scope['agent'], fn ($q) => $q->where('agent_id', $scope['agent']->id))
            ->selectRaw('EXTRACT(DOW FROM bucket_hour)::int dow, SUM(conversations_started) conversations')
            ->groupBy('dow')
            ->orderBy('dow')
            ->get();

        $hours = $byHour->map(fn ($r): array => ['hour' => (int) $r->hour, 'conversations' => (int) $r->conversations])->all();
        $days = $byDay->map(fn ($r): array => ['day' => self::DAYS[(int) $r->dow] ?? (string) $r->dow, 'conversations' => (int) $r->conversations])->all();

        $peakHour = $byHour->sortByDesc('conversations')->first();
        $peakDay = $byDay->sortByDesc('conversations')->first();

        $summary = $peakHour === null
            ? sprintf('No activity data %s yet.', $period->label)
            : sprintf(
                'Busiest %s around %02d:00 UTC%s.',
                $period->label,
                (int) $peakHour->hour,
                $peakDay ? ', most on '.(self::DAYS[(int) $peakDay->dow] ?? '') : '',
            );

        $structured = [
            'period' => ['key' => $period->key, 'label' => $period->label, 'start' => $period->start->toDateString(), 'end' => $period->end->toDateString()],
            'peak_hour_utc' => $peakHour ? (int) $peakHour->hour : null,
            'busiest_weekday' => $peakDay ? (self::DAYS[(int) $peakDay->dow] ?? null) : null,
            'by_hour' => $hours,
            'by_weekday' => $days,
        ];

        if (count($hours) > 1) {
            $structured['echarts_option'] = AnalyticsChart::bar(
                array_map(static fn (array $h): string => sprintf('%02d:00', $h['hour']), $hours),
                [['name' => 'Conversations', 'data' => array_column($hours, 'conversations')]],
                'Activity by hour (UTC) — '.$period->label,
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
