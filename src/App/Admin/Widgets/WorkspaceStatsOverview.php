<?php

declare(strict_types=1);

namespace App\Admin\Widgets;

use App\Admin\Pages\DashboardFilters;
use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Models\Workspace;
use App\Identity\Workspace\Services\MessageAllowance;
use Domain\Analytics\Data\Platform\ActivityBucketData;
use Domain\Analytics\Data\Platform\PlatformScopeData;
use Domain\Analytics\Enums\AnalyticsPeriod;
use Domain\Analytics\Queries\Platform\PlatformActivityQuery;
use Domain\Analytics\Queries\Platform\PlatformOverviewQuery;
use Domain\Analytics\Queries\Platform\ToolCallsQuery;
use Filament\Support\Icons\Heroicon;
use Filament\Widgets\StatsOverviewWidget;
use Filament\Widgets\StatsOverviewWidget\Stat;
use Illuminate\Support\Number;

/**
 * One workspace's numbers for this month on its admin page: traffic, how
 * much of its message limit is used, tool calls, leads, reply time and
 * agents. Traffic reads the same analytics as the admin dashboard.
 */
class WorkspaceStatsOverview extends StatsOverviewWidget
{
    /** The workspace the page shows; Filament passes it in. */
    public ?Workspace $record = null;

    /** The numbers change slowly; a reload is enough. */
    #[\Override]
    protected ?string $pollingInterval = null;

    /**
     * @return array<Stat>
     */
    #[\Override]
    protected function getStats(): array
    {
        if (! $this->record instanceof Workspace) {
            return [];
        }

        $scope = $this->scope($this->record);
        $overview = app(PlatformOverviewQuery::class)->get($scope);
        $series = collect(app(PlatformActivityQuery::class)->get($scope));

        return [
            StatFormat::trend(Stat::make('Messages', Number::format($overview->messages->current)), $overview->messages)
                ->icon(Heroicon::OutlinedChatBubbleLeftRight)
                ->chart($series->map(fn (ActivityBucketData $bucket): int => $bucket->messages())->all()),
            $this->messageLimit(app(MessageAllowance::class)->limitFor($this->record->id)),
            StatFormat::trend(Stat::make('Conversations', Number::format($overview->conversations->current)), $overview->conversations)
                ->icon(Heroicon::OutlinedChatBubbleOvalLeftEllipsis)
                ->chart($series->map(fn (ActivityBucketData $bucket): int => $bucket->conversations)->all()),
            Stat::make('New visitors', Number::format($overview->newVisitors))
                ->icon(Heroicon::OutlinedUserGroup)
                ->description('People who chatted for the first time'),
            Stat::make('Tool calls', Number::format(app(ToolCallsQuery::class)->count($scope)))
                ->icon(Heroicon::OutlinedWrenchScrewdriver)
                ->description('Functions and MCP tools the agents used'),
            Stat::make('Leads', Number::format($overview->leads))
                ->icon(Heroicon::OutlinedInboxArrowDown)
                ->description('Forms filled in during chats'),
            Stat::make('Average reply time', StatFormat::duration($overview->avgReplyMs))
                ->icon(Heroicon::OutlinedClock)
                ->description('Time an agent takes to answer'),
            Stat::make('Agents', Number::format($overview->totalAgents))
                ->icon(Heroicon::OutlinedCpuChip)
                ->description(Number::format($overview->newAgents).' new this month'),
        ];
    }

    /**
     * @return array<string, int>
     */
    #[\Override]
    protected function getColumns(): array
    {
        return ['@xl' => 4, '!@lg' => 4];
    }

    /**
     * The limit's own count, the one that blocks replies, not the analytics
     * total: it starts when the limit is set.
     */
    private function messageLimit(?MessageLimit $limit): Stat
    {
        $inForce = $limit?->monthly_limit;

        if ($inForce === null) {
            return Stat::make('Message limit', 'No limit')
                ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
                ->description('Agent replies are not capped');
        }

        $used = $limit?->usedThisMonth() ?? 0;
        $reached = $limit instanceof MessageLimit && $limit->reached();
        $percent = Number::format($inForce > 0 ? $used / $inForce * 100 : 0, maxPrecision: 0);
        $restarts = now()->addMonthNoOverflow()->startOfMonth()->format('j F');

        return Stat::make('Message limit', Number::format($used).' / '.Number::format($inForce))
            ->icon(Heroicon::OutlinedAdjustmentsHorizontal)
            ->description($reached
                ? "Reached: agents stop replying until {$restarts}"
                : "{$percent}% of agent replies used, restarts on {$restarts}")
            ->descriptionColor($reached ? 'danger' : null);
    }

    private function scope(Workspace $workspace): PlatformScopeData
    {
        return DashboardFilters::scope([
            DashboardFilters::PERIOD => AnalyticsPeriod::ThisMonth->value,
            DashboardFilters::WORKSPACE => $workspace->id,
        ]);
    }
}
