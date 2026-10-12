<?php

namespace Domain\Analytics\Actions\Dashboard;

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\Workspace;
use Carbon\Carbon;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Data\Dashboard\AgentCountData;
use Domain\Analytics\Data\Dashboard\DailyVolumeData;
use Domain\Analytics\Data\Dashboard\DashboardActivityData;
use Domain\Analytics\Data\Dashboard\DashboardStatsData;
use Domain\Analytics\Data\Dashboard\KnowledgeSourcesStatsData;
use Domain\Analytics\Data\Dashboard\MessagesPanelData;
use Domain\Analytics\Data\Dashboard\MessagesTrendData;
use Domain\Analytics\Data\Dashboard\TeamSizeData;
use Domain\Analytics\Services\AnalyticsCache;
use Domain\Analytics\Services\AnalyticsDatabase;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Membership\Team\Models\Team;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Illuminate\Database\Query\JoinClause;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Builds the dashboard home page for the viewer's workspace. Every number is
 * keyed to the workspace (agents and analytics rows carry its id), so a
 * teammate sees the same account-wide figures as the owner.
 *
 * Activity numbers read the analytics rollups. They are sums over the
 * workspace's whole history, so they come from the short-lived analytics
 * cache ({@see AnalyticsCache}), keyed by the workspace and today's date. The
 * agent count, the knowledge sources, the team size and the message limit's
 * counter stay live: they change with what the viewer just did. With a
 * monthly message limit, the message panel reads the limit's counter, so it
 * matches what the limit enforces — including eval cases, which never reach
 * the analytics tables.
 */
class GetDashboardStatsAction
{
    public function __construct(
        private readonly AnalyticsDatabase $analytics,
        private readonly AnalyticsCache $cache,
    ) {}

    public function execute(User $user, ?Team $team): DashboardStatsData
    {
        $workspace = $user->workspace();
        $agentIds = Agent::where('workspace_id', $workspace->id)->pluck('id');

        $activity = $this->cache->remember(
            AnalyticsCache::workspaceKey($workspace->id, 'dashboard', 'to-'.now()->toDateString()),
            fn (): DashboardActivityData => $this->activity($workspace->id),
        );

        return new DashboardStatsData(
            agents: new AgentCountData(total: $agentIds->count()),
            messages: $this->messagesPanel($workspace, $team, $activity),
            conversations: $activity->conversations,
            active_sessions: $activity->activeSessions,
            knowledge_sources: $this->knowledgeSources($agentIds),
            team: new TeamSizeData(members: $team instanceof Team ? $team->members()->count() : 0),
            weekly_usage: $activity->weeklyUsage,
            monthly_usage: $activity->monthlyUsage,
        );
    }

    private function activity(int $workspaceId): DashboardActivityData
    {
        return new DashboardActivityData(
            conversations: (int) $this->rollup()->where('workspace_id', $workspaceId)->sum('conversations_started'),
            activeSessions: $this->activeSessions($workspaceId),
            weeklyUsage: $this->dailyReplies($workspaceId, 7, 'D'),
            monthlyUsage: $this->dailyReplies($workspaceId, 30, 'j'),
            repliesThisMonth: $this->assistantMessagesBetween($workspaceId, now()->startOfMonth(), null),
            repliesLastMonth: $this->assistantMessagesBetween($workspaceId, now()->subMonth()->startOfMonth(), now()->startOfMonth()),
        );
    }

    /**
     * Visitors seen in the last 24h, across all the workspace's agents. Read
     * from the analytics event stream by workspace id, so it does not depend
     * on the agent rows still existing.
     */
    private function activeSessions(int $workspaceId): int
    {
        return $this->analytics->table('analytics_events')
            ->where('workspace_id', $workspaceId)
            ->where('occurred_at', '>=', now()->subDay())
            ->distinct()
            ->count('session_external_id');
    }

    /**
     * `total` is this month's count of the workspace's message limit when it
     * has one, otherwise this month's agent replies from the analytics rollup.
     */
    private function messagesPanel(Workspace $workspace, ?Team $team, DashboardActivityData $activity): MessagesPanelData
    {
        $limit = $workspace->messageLimit;
        $used = $limit?->usedThisMonth() ?? $activity->repliesThisMonth;

        return new MessagesPanelData(
            total: $used,
            limit: $limit?->monthly_limit,
            available: $limit === null ? null : max(0, $limit->monthly_limit - $used),
            trend: $team instanceof Team ? $this->messagesTrend($activity) : MessagesTrendData::none(),
        );
    }

    /**
     * @param  Collection<int, int>  $agentIds
     */
    private function knowledgeSources(Collection $agentIds): KnowledgeSourcesStatsData
    {
        $files = $this->attachedSources('source_files', SourceFile::class, $agentIds)
            ->distinct('source_files.id')
            ->count('source_files.id');

        $websites = $this->attachedSources('source_websites', SourceWebsite::class, $agentIds)
            ->distinct('source_websites.id')
            ->count('source_websites.id');

        $crawledPages = $this->attachedSources('source_websites', SourceWebsite::class, $agentIds)
            ->join('source_website_urls', 'source_websites.id', '=', 'source_website_urls.source_website_id')
            ->whereNotNull('source_website_urls.crawled_at')
            ->distinct('source_website_urls.id')
            ->count('source_website_urls.id');

        return new KnowledgeSourcesStatsData(
            files: $files,
            websites: $websites,
            crawled_pages: $crawledPages,
            total: $files + $websites,
        );
    }

    /**
     * Rows of a source table attached (through `agent_sources`) to any of the
     * given agents. `sourceable_type` holds the morph alias, never the class.
     *
     * @param  class-string  $model
     * @param  Collection<int, int>  $agentIds
     */
    private function attachedSources(string $table, string $model, Collection $agentIds): Builder
    {
        return DB::table($table)
            ->join('agent_sources', function (JoinClause $join) use ($table, $model): void {
                $join->on("{$table}.id", '=', 'agent_sources.sourceable_id')
                    ->where('agent_sources.sourceable_type', '=', Relation::getMorphAlias($model));
            })
            ->whereIn('agent_sources.agent_id', $agentIds);
    }

    private function messagesTrend(DashboardActivityData $activity): MessagesTrendData
    {
        $thisMonth = $activity->repliesThisMonth;
        $lastMonth = $activity->repliesLastMonth;

        $percentChange = $lastMonth > 0
            ? round((($thisMonth - $lastMonth) / $lastMonth) * 100)
            : ($thisMonth > 0 ? 100 : 0);

        return new MessagesTrendData(
            this_month: $thisMonth,
            last_month: $lastMonth,
            percent_change: $percentChange,
        );
    }

    private function assistantMessagesBetween(int $workspaceId, Carbon $from, ?Carbon $to): int
    {
        return (int) $this->rollup()
            ->where('workspace_id', $workspaceId)
            ->where('bucket_hour', '>=', $from)
            ->when($to instanceof Carbon, fn (Builder $query) => $query->where('bucket_hour', '<', $to))
            ->sum('assistant_messages');
    }

    /**
     * AI replies per day for the last `$days` days, oldest first, with every
     * day present (zero when quiet).
     *
     * @return list<DailyVolumeData>
     */
    private function dailyReplies(int $workspaceId, int $days, string $labelFormat): array
    {
        // Half-open range on the raw column — a ::date predicate can't use the index.
        $repliesByDay = $this->rollup()
            ->where('workspace_id', $workspaceId)
            ->where('bucket_hour', '>=', now()->subDays($days - 1)->startOfDay())
            ->where('bucket_hour', '<', now()->startOfDay()->addDay())
            ->selectRaw('bucket_hour::date as date, SUM(assistant_messages) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date');

        $series = [];

        for ($daysAgo = $days - 1; $daysAgo >= 0; $daysAgo--) {
            $date = now()->subDays($daysAgo);
            $dateKey = $date->format('Y-m-d');

            $series[] = new DailyVolumeData(
                date: $dateKey,
                label: $date->format($labelFormat),
                value: (int) ($repliesByDay[$dateKey] ?? 0),
            );
        }

        return $series;
    }

    private function rollup(): Builder
    {
        return $this->analytics->table('analytics_agent_hourly_rollups');
    }
}
