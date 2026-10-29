<?php

declare(strict_types=1);

namespace App\Admin\Resources\Issues\Widgets;

use App\Admin\Issue\Models\Issue;
use App\Admin\Widgets\ChartPalette;
use Carbon\CarbonInterface;
use Carbon\CarbonPeriod;
use Filament\Widgets\ChartWidget;

/**
 * How often one issue happened each day of the last 14, on its view page,
 * so an admin sees whether it is new, steady or gone.
 */
class IssueOccurrencesChart extends ChartWidget
{
    private const int DAYS = 14;

    public ?Issue $record = null;

    #[\Override]
    protected int|string|array $columnSpan = 'full';

    #[\Override]
    protected ?string $heading = 'Last 14 days';

    #[\Override]
    protected ?string $maxHeight = '180px';

    #[\Override]
    protected ?string $pollingInterval = null;

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
        $from = now()->subDays(self::DAYS - 1)->startOfDay();

        $counts = $this->record?->occurrences()->toBase()->where('occurred_at', '>=', $from)->selectRaw("to_char(date_trunc('day', occurred_at), 'YYYY-MM-DD') AS day, COUNT(*) AS total")->groupBy('day')->pluck('total', 'day') ?? collect();

        $days = collect(CarbonPeriod::create($from, now()->startOfDay())->toArray());

        return [
            'datasets' => [[
                'label' => 'Times',
                'data' => $days->map(fn (CarbonInterface $day): int => (int) ($counts[$day->toDateString()] ?? 0))->all(),
                'backgroundColor' => ChartPalette::FIRST,
                'borderRadius' => 4,
            ]],
            'labels' => $days->map(fn (CarbonInterface $day): string => $day->format('j M'))->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    protected function getOptions(): array
    {
        return [
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['y' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
