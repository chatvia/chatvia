<?php

declare(strict_types=1);

namespace App\Admin\Widgets;

use App\Admin\Pages\DashboardFilters;
use Domain\Analytics\Data\Platform\ActivityBucketData;
use Domain\Analytics\Queries\Platform\PlatformActivityQuery;
use Domain\Analytics\Queries\Platform\PlatformOverviewQuery;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\Concerns\InteractsWithPageFilters;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/**
 * The admin dashboard's headline numbers for the filtered period: traffic
 * (messages, conversations, new visitors, reply time) and growth
 * (workspaces, agents, leads). Workspace counts are left out while one
 * workspace is chosen.
 */
class PlatformStatsOverview extends StatsOverviewWidget
{
    use InteractsWithPageFilters;

    #[\Override]
    protected static ?int $sort = 1;

    /** The numbers change slowly; the filters re-render it. */
    #[\Override]
    protected ?string $pollingInterval = null;

    /**
     * @return array<Stat>
     */
    #[\Override]
    protected function getStats(): array
    {
        $scope = DashboardFilters::scope($this->pageFilters);
        $overview = app(PlatformOverviewQuery::class)->get($scope);
        $series = collect(app(PlatformActivityQuery::class)->get($scope));

        return array_values(array_filter([
            StatFormat::trend(Stat::make('Messages', Number::format($overview->messages->current)), $overview->messages)
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->chart($series->map(fn (ActivityBucketData $bucket): int => $bucket->messages())->all()),
            StatFormat::trend(Stat::make('Conversations', Number::format($overview->conversations->current)), $overview->conversations)
                ->icon(Heroicon::OutlinedChatBubbleOvalLeftEllipsis)
                ->chart($series->map(fn (ActivityBucketData $bucket): int => $bucket->conversations)->all()),
            Stat::make('New visitors', Number::format($overview->newVisitors))
                ->icon(Heroicon::OutlinedUserGroup)
                ->description('People who chatted for the first time'),
            Stat::make('Average reply time', StatFormat::duration($overview->avgReplyMs))
                ->icon(Heroicon::OutlinedClock)
                ->description('Time an agent takes to answer'),
            $scope->isWholePlatform()
                ? StatFormat::trend(Stat::make('New workspaces', Number::format($overview->newWorkspaces->current)), $overview->newWorkspaces)
                    ->icon(Heroicon::OutlinedBuildingOffice2)
                    ->chart($series->map(fn (ActivityBucketData $bucket): int => $bucket->newWorkspaces)->all())
                : null,
            $scope->isWholePlatform()
                ? Stat::make('Active workspaces', Number::format($overview->activeWorkspaces))
                    ->icon(Heroicon::OutlinedBolt)
                    ->description('Had messages, out of '.Number::format($overview->totalWorkspaces).' in total')
                : null,
            Stat::make('Agents', Number::format($overview->totalAgents))
                ->icon(Heroicon::OutlinedCpuChip)
                ->description(Number::format($overview->newAgents).' new in this period'),
            Stat::make('Leads', Number::format($overview->leads))
                ->icon(Heroicon::OutlinedInboxArrowDown)
                ->description('Forms filled in during chats'),
        ]));
    }

    /**
     * Four to a row, so the traffic and growth numbers each fill one row.
     *
     * @return array<string, int>
     */
    #[\Override]
    protected function getColumns(): array
    {
        $columns = count($this->getCachedStats()) % 4 === 0 ? 4 : 3;

        return ['@xl' => $columns, '!@lg' => $columns];
    }
}
