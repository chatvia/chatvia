<?php

declare(strict_types=1);

namespace App\Admin\Widgets;

use App\Admin\Pages\DashboardFilters;
use Domain\Analytics\Data\Account\Audience\ChannelShareData;
use Domain\Analytics\Queries\Platform\PlatformChannelQuery;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\ChartWidget;
use Filament\Widgets\Concerns\InteractsWithPageFilters;

/**
 * Conversations started in the filtered period per channel (Hosted Chat,
 * Chat Widget, WhatsApp, …), busiest first. One bar per channel, so nine
 * channels never need nine colors.
 */
class ChannelsChart extends ChartWidget
{
    use InteractsWithPageFilters;

    #[\Override]
    protected static ?int $sort = 3;

    #[\Override]
    protected ?string $heading = 'Conversations by channel';

    #[\Override]
    protected ?string $maxHeight = '280px';

    #[\Override]
    protected ?string $pollingInterval = null;

    #[\Override]
    protected ?string $emptyStateHeading = 'No conversations in this period';

    #[\Override]
    protected string|\BackedEnum|null $emptyStateIcon = Heroicon::OutlinedChatBubbleLeftRight;

    #[\Override]
    public function getDescription(): string
    {
        return 'Where conversations started, '.DashboardFilters::scope($this->pageFilters)->period->label.'.';
    }

    #[\Override]
    protected function getType(): string
    {
        return 'bar';
    }

    /**
     * Empty when there were no conversations, so the widget shows its empty
     * state instead of a blank chart.
     *
     * @return array<string, mixed>
     */
    #[\Override]
    protected function getData(): array
    {
        $channels = collect(app(PlatformChannelQuery::class)->get(DashboardFilters::scope($this->pageFilters))->channels);

        if ($channels->isEmpty()) {
            return [];
        }

        return [
            'datasets' => [[
                'label' => 'Conversations',
                'data' => $channels->map(fn (ChannelShareData $channel): int => $channel->conversations)->all(),
                'backgroundColor' => ChartPalette::FIRST,
                'borderWidth' => 0,
                'borderRadius' => 4,
                'maxBarThickness' => 28,
            ]],
            'labels' => $channels->map(fn (ChannelShareData $channel): string => $channel->label)->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    #[\Override]
    protected function getOptions(): array
    {
        return [
            'indexAxis' => 'y',
            'plugins' => ['legend' => ['display' => false]],
            'scales' => ['x' => ['beginAtZero' => true, 'ticks' => ['precision' => 0]]],
        ];
    }
}
