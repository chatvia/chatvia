<?php

namespace Domain\Analytics\Actions\Dashboard;

use App\Identity\Workspace\Models\User;
use Carbon\Carbon;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Data\Dashboard\AgentCountData;
use Domain\Analytics\Data\Dashboard\DailyVolumeData;
use Domain\Analytics\Data\Dashboard\DashboardStatsData;
use Domain\Analytics\Data\Dashboard\KnowledgeSourcesStatsData;
use Domain\Analytics\Data\Dashboard\MessagesPanelData;
use Domain\Analytics\Data\Dashboard\MessagesTrendData;
use Domain\Analytics\Data\Dashboard\TeamSizeData;
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
 * keyed to the workspace OWNER (agents and analytics rows are stamped with the
 * owner's id), so a teammate sees the same account-wide figures as the owner.
 *
 * Activity numbers read the analytics rollups. With a monthly message limit,
 * the message panel reads the limit's counter instead, so it matches what the
 * limit enforces — including eval cases, which never reach the analytics
 * tables.
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

        return new DashboardStatsData(
            agents: new AgentCountData(total: $agentIds->count()),
            messages: $this->messagesPanel($owner, $team),
            conversations: (int) $this->rollup()->where('workspace_id', $owner->id)->sum('conversations_started'),
            active_sessions: $this->activeSessions($owner->id),
            knowledge_sources: $this->knowledgeSources($agentIds),
            team: new TeamSizeData(members: $team instanceof Team ? $team->members()->count() : 0),
            weekly_usage: $this->dailyReplies($owner->id, 7, 'D'),
            monthly_usage: $this->dailyReplies($owner->id, 30, 'j'),
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
    private function messagesPanel(User $owner, ?Team $team): MessagesPanelData
    {
        $limit = $owner->messageLimit;
        $used = $limit?->usedThisMonth() ?? $this->assistantMessagesBetween($owner->id, now()->startOfMonth(), null);

        return new MessagesPanelData(
            total: $used,
            limit: $limit?->monthly_limit,
            available: $limit === null ? null : max(0, $limit->monthly_limit - $used),
            trend: $team instanceof Team ? $this->messagesTrend($owner->id) : MessagesTrendData::none(),
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
