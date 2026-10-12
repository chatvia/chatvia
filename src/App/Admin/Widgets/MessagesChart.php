<?php

declare(strict_types=1);

namespace App\Admin\Widgets;

use App\Admin\Pages\DashboardFilters;
use Domain\Analytics\Data\Platform\ActivityBucketData;
use Domain\Analytics\Queries\Platform\PlatformActivityQuery;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * AI replies and visitor messages over the filtered period, per hour, day or
 * month depending on its length.
 */
class MessagesChart extends ChartWidget
{
    use InteractsWithPageFilters;

    #[\Override]
    protected static ?int $sort = 2;

    #[\Override]
    protected int|string|array $columnSpan = 'full';

    #[\Override]
    protected ?string $heading = 'Messages';

    #[\Override]
    protected ?string $maxHeight = '300px';

    #[\Override]
    protected ?string $pollingInterval = null;

    #[\Override]
    public function getDescription(): string
    {
        return 'AI replies and visitor messages, '.DashboardFilters::scope($this->pageFilters)->period->label.'.';
    }

    #[\Override]
    protected function getType(): string
    {
        return 'line';
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
                $this->line('AI replies', ChartPalette::FIRST, $series->map(fn (ActivityBucketData $bucket): int => $bucket->aiReplies)->all()),
                $this->line('Visitor messages', ChartPalette::SECOND, $series->map(fn (ActivityBucketData $bucket): int => $bucket->visitorMessages)->all()),
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
    private function line(string $label, string $color, array $data): array
    {
        return [
            'label' => $label,
            'data' => $data,
            'borderColor' => $color,
            'backgroundColor' => $color,
            'borderWidth' => 2,
            'pointRadius' => 0,
            'pointHoverRadius' => 4,
            'tension' => 0.3,
        ];
    }
}
