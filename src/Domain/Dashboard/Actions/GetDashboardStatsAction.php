<?php

namespace Domain\Dashboard\Actions;

use App\Identity\Workspace\Models\User;
use Carbon\Carbon;
use Domain\Agent\Models\Agent;
use Domain\Billing\Models\UserQuota;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Domain\Membership\Team\Models\Team;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class GetDashboardStatsAction
{
    /**
     * @return array<string, mixed>
     */
    public function execute(User $user, ?Team $team): array
    {
        // Resolve the workspace owner. agent.user_id — and therefore every
        // analytics row — is stamped with it (see CreateAgentAction), so all
        // account-level stats key on this id and stay correct no matter which
        // member is viewing the dashboard.
        $owner = $user->workspace();
        $userId = $owner->id;

        // Every agent the workspace owns, by user_id. Drives the "Total Agents"
        // count and the knowledge-sources breakdown — scoped the same way as the
        // rest of the dashboard.
        $agentIds = Agent::where('user_id', $userId)->pluck('id');

        $totalConversations = $this->rollup()
            ->where('workspace_id', $userId)
            ->sum('conversations_started');

        return [
            'agents' => [
                'total' => $agentIds->count(),
            ],
            'messages' => $this->getMessagesPanel($owner, $team),
            'conversations' => (int) $totalConversations,
            'active_sessions' => $this->getActiveSessions($userId),
            'knowledge_sources' => $this->getKnowledgeSourcesBreakdown($agentIds),
            'team' => [
                'members' => $team ? $team->members()->count() : 0,
            ],
            'weekly_usage' => $this->getDailyMessagesVolume($userId, 7, 'D'),
            'monthly_usage' => $this->getDailyMessagesVolume($userId, 30, 'j'),
            'usage' => $this->getUsageStats($owner),
        ];
    }

    /**
     * Visitors seen in the last 24h, across all the user's agents. Sourced from
     * the analytics event stream by user_id — a recency indicator that no longer
     * depends on the agent rows still existing.
     */
    private function getActiveSessions(int $userId): int
    {
        return DB::connection(config('chatvia.analytics.connection'))
            ->table('analytics_events')
            ->where('workspace_id', $userId)
            ->where('occurred_at', '>=', now()->subDay())
            ->distinct()
            ->count('session_external_id');
    }

    /**
     * Build the headline "Messages This Cycle" panel.
     *
     * `used` is sourced from the analytics rollup (by user_id) so it survives
     * chat deletions — but the cycle bounds and base quota still come from the
     * user's active UserCredit row (billing source of truth). Addons are a
     * SEPARATE pool that survives base-quota overrun: matches the long-standing
     * UserCreditSystem::calculateAvailableMessages semantics where addons stay
     * intact even when base usage > base quota.
     *
     * @return array<string, mixed>
     */
    private function getMessagesPanel(User $user, ?Team $team): array
    {
        $credits = $user->messageCredits();

        return [
            'total' => $credits->used(),
            'available' => $credits->available(),
            'limit' => $credits->limit(),
            'cycle' => $credits->cycleBounds(),
            'trend' => $this->getMessagesTrend($user->id, $team !== null),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function getUsageStats(User $user): array
    {
        /** @var UserQuota $quota */
        $quota = $user->quota;

        $credits = $user->messageCredits();
        $consumption = $user->consumption();
        $pagesConsumed = $consumption->pages();
        $minutesConsumed = $consumption->minutes();
        $linksConsumed = $consumption->links();

        return [
            'plan' => $user->plan ?? 'Free',
            'messages' => [
                'used' => $credits->used(),
                'limit' => $credits->limit(),
                'remaining' => $credits->available(),
            ],
            'pages' => [
                'used' => $pagesConsumed,
                'limit' => $quota->pages_limit,
                'remaining' => max(0, $quota->pages_limit - $pagesConsumed),
            ],
            'minutes' => [
                'used' => $minutesConsumed,
                'limit' => $quota->minutes_limit,
                'remaining' => max(0, $quota->minutes_limit - $minutesConsumed),
            ],
            'links' => [
                'used' => $linksConsumed,
                'limit' => $quota->links_limit,
                'remaining' => max(0, $quota->links_limit - $linksConsumed),
            ],
        ];
    }

    /**
     * @param  Collection<int, int>  $agentIds
     * @return array<string, int>
     */
    private function getKnowledgeSourcesBreakdown($agentIds): array
    {
        $files = DB::table('source_files')
            ->join('agent_sources', function ($join): void {
                $join->on('source_files.id', '=', 'agent_sources.sourceable_id')
                    ->where('agent_sources.sourceable_type', '=', Relation::getMorphAlias(SourceFile::class));
            })
            ->whereIn('agent_sources.agent_id', $agentIds)
            ->distinct('source_files.id')
            ->count('source_files.id');

        $websites = DB::table('source_websites')
            ->join('agent_sources', function ($join): void {
                $join->on('source_websites.id', '=', 'agent_sources.sourceable_id')
                    ->where('agent_sources.sourceable_type', '=', Relation::getMorphAlias(SourceWebsite::class));
            })
            ->whereIn('agent_sources.agent_id', $agentIds)
            ->distinct('source_websites.id')
            ->count('source_websites.id');

        $crawledPages = DB::table('source_website_urls')
            ->join('source_websites', 'source_websites.id', '=', 'source_website_urls.source_website_id')
            ->join('agent_sources', function ($join): void {
                $join->on('source_websites.id', '=', 'agent_sources.sourceable_id')
                    ->where('agent_sources.sourceable_type', '=', Relation::getMorphAlias(SourceWebsite::class));
            })
            ->whereIn('agent_sources.agent_id', $agentIds)
            ->whereNotNull('source_website_urls.crawled_at')
            ->distinct('source_website_urls.id')
            ->count('source_website_urls.id');

        return [
            'files' => $files,
            'websites' => $websites,
            'crawled_pages' => $crawledPages,
            'total' => $files + $websites,
        ];
    }

    /**
     * @return array<string, int|float>
     */
    private function getMessagesTrend(int $userId, bool $hasTeam): array
    {
        if (! $hasTeam) {
            return ['this_month' => 0, 'last_month' => 0, 'percent_change' => 0];
        }

        $thisMonth = $this->assistantMessagesInRange($userId, now()->startOfMonth(), null);
        $lastMonth = $this->assistantMessagesInRange($userId, now()->subMonth()->startOfMonth(), now()->startOfMonth());

        $percentChange = $lastMonth > 0
            ? round((($thisMonth - $lastMonth) / $lastMonth) * 100)
            : ($thisMonth > 0 ? 100 : 0);

        return [
            'this_month' => $thisMonth,
            'last_month' => $lastMonth,
            'percent_change' => $percentChange,
        ];
    }

    private function assistantMessagesInRange(int $userId, Carbon $from, ?Carbon $to): int
    {
        $query = $this->rollup()
            ->where('workspace_id', $userId)
            ->where('bucket_hour', '>=', $from);

        if ($to) {
            $query->where('bucket_hour', '<', $to);
        }

        return (int) $query->sum('assistant_messages');
    }

    /**
     * @return array<int, array{label: string, value: int}>
     */
    private function getDailyMessagesVolume(int $userId, int $days, string $labelFormat): array
    {
        $startDate = now()->subDays($days - 1)->startOfDay();
        $endDate = now()->endOfDay();

        $dailyMessages = $this->rollup()
            ->where('workspace_id', $userId)
            ->whereRaw('bucket_hour::date BETWEEN ? AND ?', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->selectRaw('bucket_hour::date as date, SUM(assistant_messages) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        $data = [];

        for ($i = $days - 1; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dateKey = $date->format('Y-m-d');

            $data[] = [
                'label' => $date->format($labelFormat),
                'value' => (int) ($dailyMessages[$dateKey] ?? 0),
            ];
        }

        return $data;
    }

    /**
     * Single entry point for the analytics rollup query builder. Pinned to
     * the configured analytics connection so the whole dashboard reads from
     * the isolated DB when one's set up.
     */
    private function rollup(): Builder
    {
        return DB::connection(config('chatvia.analytics.connection'))
            ->table('analytics_agent_hourly_rollups');
    }
}
