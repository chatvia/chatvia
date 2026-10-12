<?php

declare(strict_types=1);

namespace App\Admin\Widgets;

use App\Admin\Pages\DashboardFilters;
use Domain\Analytics\Data\Platform\ActivityBucketData;
use Domain\Analytics\Queries\Platform\PlatformActivityQuery;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * New workspaces and new agents over the filtered period. Shown only for the
 * whole platform: one workspace's sign-ups are always one.
 */
class GrowthChart extends ChartWidget
{
    use InteractsWithPageFilters;

    #[\Override]
    protected static ?int $sort = 4;

    #[\Override]
    protected ?string $heading = 'Growth';

    #[\Override]
    protected ?string $maxHeight = '280px';

    #[\Override]
    protected ?string $pollingInterval = null;

    #[\Override]
    public function getDescription(): string
    {
        return 'New workspaces and agents, '.DashboardFilters::scope($this->pageFilters)->period->label.'.';
    }

    #[\Override]
    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    protected function getData(): array
    {
        $series = collect(app(PlatformActivityQuery::class)->get(DashboardFilters::scope($this->pageFilters)));

        return [
            'datasets' => [
                $this->bars('New workspaces', ChartPalette::FIRST, $series->map(fn (ActivityBucketData $bucket): int => $bucket->newWorkspaces)->all()),
                $this->bars('New agents', ChartPalette::SECOND, $series->map(fn (ActivityBucketData $bucket): int => $bucket->newAgents)->all()),
            ],
            'labels' => $series->map(fn (ActivityBucketData $bucket): string => $bucket->label)->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    protected function getOptions(): array
    {
        return [
            'interaction' => ['mode' => 'index', 'intersect' => false],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }

    /**
     * @param  list<int>  $data
     * @return array<string, mixed>
     */
    private function bars(string $label, string $color, array $data): array
    {
        return [
            'label' => $label,
            'data' => $data,
            'backgroundColor' => $color,
            'borderWidth' => 0,
            'borderRadius' => 4,
            'maxBarThickness' => 24,
        ];
    }
}
