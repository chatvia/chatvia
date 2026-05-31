<?php

namespace Domain\Dashboard\Actions;

use App\Auth\Models\User;
use App\Billing\Models\UserQuota;
use App\Teams\Models\Team;
use Carbon\Carbon;
use Domain\Agents\Models\Lead;
use Domain\Sources\Files\Models\SourceFile;
use Domain\Sources\Web\Models\SourceWebsite;
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
        // One round-trip for the agents id list — reused for every per-actor
        // count below and for the messages trend.
        $agentIds = $team ? $team->agents()->pluck('id') : collect();

        $totalConversations = $this->rollup()
            ->whereIn('agent_id', $agentIds)
            ->sum('conversations_started');

        $totalLeads = Lead::whereIn('agent_id', $agentIds)->count();

        // "Active sessions" intentionally still reads chat_sessions: this is a
        // recency indicator ("who pinged us in the last 24h"), not a preserved
        // counter — survives-deletion semantics aren't what we want here.
        $activeSessions = DB::table('chat_sessions')
            ->whereIn('agent_id', $agentIds)
            ->where('created_at', '>=', now()->subDay())
            ->count();

        return [
            'agents' => [
                'total' => $agentIds->count(),
            ],
            'messages' => $this->getMessagesPanel($user, $agentIds, $team),
            'conversations' => (int) $totalConversations,
            'leads' => $totalLeads,
            'active_sessions' => $activeSessions,
            'knowledge_sources' => $this->getKnowledgeSourcesBreakdown($agentIds),
            'team' => [
                'members' => $team ? $team->members()->count() : 0,
            ],
            'weekly_usage' => $this->getDailyMessagesVolume($agentIds, 7, 'D'),
            'monthly_usage' => $this->getDailyMessagesVolume($agentIds, 30, 'j'),
            'usage' => $this->getUsageStats($user, $agentIds),
        ];
    }

    /**
     * Build the headline "Messages This Cycle" panel.
     *
     * `used` is sourced from the analytics rollup so it survives chat deletions
     * — but the cycle bounds and base quota still come from the user's active
     * UserCredit row (billing source of truth). Addons are a SEPARATE pool that
     * survives base-quota overrun: matches the long-standing
     * UserCreditSystem::calculateAvailableMessages semantics where addons stay
     * intact even when base usage > base quota.
     *
     * @param  Collection<int, int>  $agentIds
     * @return array<string, mixed>
     */
    private function getMessagesPanel(User $user, Collection $agentIds, ?Team $team): array
    {
        $credit = $user->credit;

        $used = $credit
            ? (int) $this->cycleUsage($agentIds, $credit->started_at)
            : 0;

        $baseLimit = $credit ? (int) $credit->messages : 0;
        $addons = $this->activeAddonsTotal($user);

        // remaining base, clamped — then addons added on top.
        $available = max(0, $baseLimit - $used) + $addons;
        $limit = $baseLimit + $addons;

        return [
            'total' => $used,
            'available' => $available,
            'limit' => $limit,
            'cycle' => $credit
                ? [
                    'started_at' => $credit->started_at,
                    'ends_at' => $credit->ends_at,
                ]
                : null,
            'trend' => $this->getMessagesTrend($agentIds, $team !== null),
        ];
    }

    /**
     * @param  Collection<int, int>  $agentIds
     */
    private function cycleUsage(Collection $agentIds, Carbon $cycleStart): int
    {
        return (int) $this->rollup()
            ->whereIn('agent_id', $agentIds)
            ->where('bucket_hour', '>=', $cycleStart)
            ->sum('assistant_messages');
    }

    private function activeAddonsTotal(User $user): int
    {
        return (int) $user->addons()
            ->where('type', 'extra_message_credits')
            ->where('ends_at', '>=', now())
            ->sum('amount');
    }

    /**
     * @return array<string, mixed>
     */
    private function getUsageStats(User $user, Collection $agentIds): array
    {
        /** @var UserQuota $quota */
        $quota = $user->quota;
        $credit = $user->credit;

        $cycleMessages = $credit ? $this->cycleUsage($agentIds, $credit->started_at) : 0;
        $baseLimit = $credit ? (int) $credit->messages : 0;
        $addons = $this->activeAddonsTotal($user);
        $quotaMessages = $baseLimit + $addons;
        $remaining = max(0, $baseLimit - $cycleMessages) + $addons;

        $pagesConsumed = $user->pagesConsumed();
        $minutesConsumed = $user->minutesConsumed();
        $linksConsumed = $user->linksConsumed();

        return [
            'plan' => $user->plan ?? 'Free',
            'messages' => [
                'used' => $cycleMessages,
                'limit' => $quotaMessages,
                'remaining' => $remaining,
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
                    ->where('agent_sources.sourceable_type', '=', SourceFile::class);
            })
            ->whereIn('agent_sources.agent_id', $agentIds)
            ->distinct('source_files.id')
            ->count('source_files.id');

        $websites = DB::table('source_websites')
            ->join('agent_sources', function ($join): void {
                $join->on('source_websites.id', '=', 'agent_sources.sourceable_id')
                    ->where('agent_sources.sourceable_type', '=', SourceWebsite::class);
            })
            ->whereIn('agent_sources.agent_id', $agentIds)
            ->distinct('source_websites.id')
            ->count('source_websites.id');

        $crawledPages = DB::table('source_website_urls')
            ->join('source_websites', 'source_websites.id', '=', 'source_website_urls.source_website_id')
            ->join('agent_sources', function ($join): void {
                $join->on('source_websites.id', '=', 'agent_sources.sourceable_id')
                    ->where('agent_sources.sourceable_type', '=', SourceWebsite::class);
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
     * @param  Collection<int, int>  $agentIds
     * @return array<string, int|float>
     */
    private function getMessagesTrend(Collection $agentIds, bool $hasTeam): array
    {
        if (! $hasTeam) {
            return ['this_month' => 0, 'last_month' => 0, 'percent_change' => 0];
        }

        $thisMonth = $this->assistantMessagesInRange($agentIds, now()->startOfMonth(), null);
        $lastMonth = $this->assistantMessagesInRange($agentIds, now()->subMonth()->startOfMonth(), now()->startOfMonth());

        $percentChange = $lastMonth > 0
            ? round((($thisMonth - $lastMonth) / $lastMonth) * 100)
            : ($thisMonth > 0 ? 100 : 0);

        return [
            'this_month' => $thisMonth,
            'last_month' => $lastMonth,
            'percent_change' => $percentChange,
        ];
    }

    /**
     * @param  Collection<int, int>  $agentIds
     */
    private function assistantMessagesInRange(Collection $agentIds, Carbon $from, ?Carbon $to): int
    {
        $query = $this->rollup()
            ->whereIn('agent_id', $agentIds)
            ->where('bucket_hour', '>=', $from);

        if ($to) {
            $query->where('bucket_hour', '<', $to);
        }

        return (int) $query->sum('assistant_messages');
    }

    /**
     * @param  Collection<int, int>  $agentIds
     * @return array<int, array{label: string, value: int}>
     */
    private function getDailyMessagesVolume(Collection $agentIds, int $days, string $labelFormat): array
    {
        $data = [];

        if ($agentIds->isEmpty()) {
            for ($i = $days - 1; $i >= 0; $i--) {
                $data[] = ['label' => now()->subDays($i)->format($labelFormat), 'value' => 0];
            }

            return $data;
        }

        $startDate = now()->subDays($days - 1)->startOfDay();
        $endDate = now()->endOfDay();

        $dailyMessages = $this->rollup()
            ->whereIn('agent_id', $agentIds)
            ->whereRaw('bucket_hour::date BETWEEN ? AND ?', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->selectRaw('bucket_hour::date as date, SUM(assistant_messages) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

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
        return DB::connection(config('analytics.connection'))
            ->table('analytics_agent_hourly_rollups');
    }
}
