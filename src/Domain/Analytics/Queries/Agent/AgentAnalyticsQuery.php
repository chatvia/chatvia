<?php

namespace Domain\Analytics\Queries\Agent;

use Domain\Agent\Models\Agent;
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
 */
abstract class AgentAnalyticsQuery
{
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

    /**
     * The agent's rows in a per-day rollup table.
     */
    protected function dailyRollup(Agent $agent, string $table): Builder
    {
        return $this->analytics()->table($table)->where('agent_id', $agent->id);
    }
}
