<?php

namespace Domain\Analytics\Actions\Dashboard;

use App\Identity\Workspace\Models\User;
use Carbon\Carbon;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Data\Dashboard\AgentCountData;
use Domain\Analytics\Data\Dashboard\BillingCycleData;
use Domain\Analytics\Data\Dashboard\DailyVolumeData;
use Domain\Analytics\Data\Dashboard\DashboardStatsData;
use Domain\Analytics\Data\Dashboard\KnowledgeSourcesStatsData;
use Domain\Analytics\Data\Dashboard\MessagesPanelData;
use Domain\Analytics\Data\Dashboard\MessagesTrendData;
use Domain\Analytics\Data\Dashboard\QuotaUsageData;
use Domain\Analytics\Data\Dashboard\TeamSizeData;
use Domain\Analytics\Data\Dashboard\UsageStatsData;
use Domain\Analytics\Services\AnalyticsDatabase;
use Domain\Billing\Services\MessageCredits;
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
 * keyed to the workspace OWNER (agents and analytics rows are stamped with the
 * owner's id), so a teammate sees the same account-wide figures as the owner.
 *
 * Activity numbers read the analytics rollups; the message panel reads the
 * billing counter instead, because it must match what the customer is billed
 * on — including eval cases, which never reach the analytics tables.
 */
class GetDashboardStatsAction
{
    public function __construct(
        private readonly AnalyticsDatabase $analytics,
    ) {}

    public function execute(User $user, ?Team $team): DashboardStatsData
    {
        $owner = $user->workspace();
        $agentIds = Agent::where('user_id', $owner->id)->pluck('id');

        // One memoized MessageCredits instance for both panels.
        $credits = $owner->messageCredits();

        return new DashboardStatsData(
            agents: new AgentCountData(total: $agentIds->count()),
            messages: $this->messagesPanel($owner, $team, $credits),
            conversations: (int) $this->rollup()->where('workspace_id', $owner->id)->sum('conversations_started'),
            active_sessions: $this->activeSessions($owner->id),
            knowledge_sources: $this->knowledgeSources($agentIds),
            team: new TeamSizeData(members: $team ? $team->members()->count() : 0),
            weekly_usage: $this->dailyReplies($owner->id, 7, 'D'),
            monthly_usage: $this->dailyReplies($owner->id, 30, 'j'),
            usage: $this->usage($owner, $credits),
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
     * `total` is the billing counter (user_usages.messages via MessageCredits)
     * — the same number the Usage page shows. Add-ons are a separate pool that
     * survives a base-quota overrun.
     */
    private function messagesPanel(User $owner, ?Team $team, MessageCredits $credits): MessagesPanelData
    {
        $cycle = $credits->cycleBounds();

        return new MessagesPanelData(
            total: $credits->used(),
            available: $credits->available(),
            limit: $credits->limit(),
            cycle: $cycle === null ? null : new BillingCycleData($cycle['started_at'], $cycle['ends_at']),
            trend: $team === null ? MessagesTrendData::none() : $this->messagesTrend($owner->id),
        );
    }

    private function usage(User $owner, MessageCredits $credits): UsageStatsData
    {
        $consumption = $owner->consumption();

        return new UsageStatsData(
            plan: $owner->plan ?? 'Free',
            messages: new QuotaUsageData(used: $credits->used(), limit: $credits->limit(), remaining: $credits->available()),
            pages: QuotaUsageData::of($consumption->pages(), $owner->quotaLimit('pages_limit')),
            minutes: QuotaUsageData::of($consumption->minutes(), $owner->quotaLimit('minutes_limit')),
            links: QuotaUsageData::of($consumption->links(), $owner->quotaLimit('links_limit')),
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

    private function messagesTrend(int $workspaceId): MessagesTrendData
    {
        $thisMonth = $this->assistantMessagesBetween($workspaceId, now()->startOfMonth(), null);
        $lastMonth = $this->assistantMessagesBetween($workspaceId, now()->subMonth()->startOfMonth(), now()->startOfMonth());

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
            ->when($to !== null, fn (Builder $query) => $query->where('bucket_hour', '<', $to))
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
