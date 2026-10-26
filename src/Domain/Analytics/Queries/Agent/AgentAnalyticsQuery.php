<?php

namespace Domain\Analytics\Queries\Agent;

use Closure;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Enums\RollupGrain;
use Domain\Analytics\Services\AnalyticsCache;
use Illuminate\Database\Connection;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Shared building blocks for the per-agent analytics read models — the
 * numbers on an agent's Analytics pages. They read the durable analytics
 * tables (rollups, per-conversation metrics, events) by `agent_id`, so the
 * counts survive deletion of the raw chat rows.
 *
 * The analytics tables may live on a separate database
 * (`chatvia.analytics.connection`), so a query never joins them to main-DB
 * tables; names (countries, topics) are looked up on the default connection.
 *
 * The page-level numbers go through {@see cached()}: a few minutes old, but
 * the sums over an agent's whole history are not run on every page view.
 */
abstract class AgentAnalyticsQuery
{
    public function __construct(
        private readonly AnalyticsCache $cache,
    ) {}

    /**
     * One of the agent's numbers from the short-lived analytics cache, keyed
     * by what it counts and its date range (`lifetime` for all time).
     *
     * @template TValue
     *
     * @param  Closure(): TValue  $compute
     * @return TValue
     */
    protected function cached(Agent $agent, string $what, string $range, Closure $compute): mixed
    {
        return $this->cache->remember(AnalyticsCache::agentKey($agent->workspace_id, $agent->id, $what, $range), $compute);
    }

    /**
     * A number about several of a workspace's agents from the short-lived
     * analytics cache.
     *
     * @template TValue
     *
     * @param  Closure(): TValue  $compute
     * @return TValue
     */
    protected function cachedForWorkspace(int $workspaceId, string $what, string $range, Closure $compute): mixed
    {
        return $this->cache->remember(AnalyticsCache::workspaceKey($workspaceId, $what, $range), $compute);
    }

    /** The analytics DB connection (may be a separate Postgres database). */
    protected function analytics(): Connection
    {
        return DB::connection(config('chatvia.analytics.connection'));
    }

    /** The agent's rows in the per-agent hourly rollup. */
    protected function hourlyRollups(Agent $agent): Builder
    {
        return $this->analytics()->table('analytics_agent_hourly_rollups')->where('agent_id', $agent->id);
    }

    /** The agent's rows in the hourly or the daily rollup. */
    protected function rollupOf(Agent $agent, RollupGrain $grain): Builder
    {
        return $this->analytics()->table($grain->table())->where('agent_id', $agent->id);
    }

    /**
     * The agent's rows in a per-day rollup table.
     */
    protected function dailyRollup(Agent $agent, string $table): Builder
    {
        return $this->analytics()->table($table)->where('agent_id', $agent->id);
    }
}
