<?php

namespace Domain\Agent\QueryBuilders;

use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * @template TModel of Agent
 *
 * @extends Builder<TModel>
 */
class AgentQueryBuilder extends Builder
{
    /**
     * Add total members count as a subquery select.
     */
    public function withTotalMembers(): self
    {
        return $this->addSelect([
            'total_members' => DB::table('users')
                ->whereColumn('users.workspace_id', 'agents.user_id')
                ->selectRaw('count(*)'),
        ]);
    }

    /**
     * Single-pass aggregate sourced from the analytics rollups (configured DB
     * connection). Counts survive deletion of the underlying chat rows.
     *
     * Returns: total_assistant, total_user, total_conversations, avg_response_ms,
     * total_tokens, avg_tokens, positive_feedback, negative_feedback,
     * conversations_with_feedback, avg_messages_per_conversation,
     * avg_conversation_duration_seconds, avg_first_response_seconds, peak_hour,
     * first_data_date, unique_sessions.
     */
    public function aggregateStats(): object
    {
        $agentId = $this->getModel()->id;
        $connection = config('chatvia.analytics.connection');

        $sql = <<<'SQL'
WITH agent_rollup AS (
    SELECT
        COALESCE(SUM(user_messages), 0)             AS total_user,
        COALESCE(SUM(assistant_messages), 0)        AS total_assistant,
        COALESCE(SUM(conversations_started), 0)     AS total_conversations,
        CASE WHEN SUM(response_ms_count) > 0
             THEN SUM(response_ms_sum)::float / SUM(response_ms_count)
        END                                         AS avg_response_ms,
        COALESCE(SUM(total_tokens), 0)              AS total_tokens,
        CASE WHEN SUM(assistant_messages) > 0
             THEN SUM(total_tokens)::float / SUM(assistant_messages)
        END                                         AS avg_tokens,
        MIN(bucket_hour)::date                      AS first_data_date
    FROM analytics_agent_hourly_rollups
    WHERE agent_id = ?
),
hour_bucket AS (
    SELECT EXTRACT(HOUR FROM bucket_hour)::int AS hour,
           SUM(conversations_started)          AS c
    FROM analytics_agent_hourly_rollups
    WHERE agent_id = ?
    GROUP BY 1
    ORDER BY c DESC NULLS LAST
    LIMIT 1
),
-- avg_first_response_seconds reads the incrementally-maintained first_*_at pair.
conv_metrics AS (
    SELECT
        AVG(message_count)                                    AS avg_messages_per_conversation,
        AVG(NULLIF(duration_seconds, 0))                      AS avg_conversation_duration_seconds,
        AVG(EXTRACT(EPOCH FROM (first_assistant_at - first_user_at)))
            FILTER (WHERE first_assistant_at > first_user_at) AS avg_first_response_seconds
    FROM analytics_conversation_metrics
    WHERE agent_id = ?
),
session_count AS (
    SELECT COUNT(*) AS unique_sessions
    FROM analytics_session_firsts
    WHERE agent_id = ?
),
-- Feedback is current-state (one upserted row per message), so totals are a
-- plain COUNT over the live rows rather than a lifetime rollup counter.
feedback AS (
    SELECT
        COUNT(*) FILTER (WHERE feedback = 'positive')   AS positive_feedback,
        COUNT(*) FILTER (WHERE feedback = 'negative')   AS negative_feedback,
        COUNT(DISTINCT conversation_external_id)        AS conversations_with_feedback
    FROM analytics_events
    WHERE agent_id = ? AND event_type = 'feedback_given'
)
SELECT
    ar.total_assistant,
    ar.total_user,
    ar.total_conversations,
    ar.avg_response_ms,
    ar.total_tokens,
    ar.avg_tokens,
    fb.positive_feedback,
    fb.negative_feedback,
    ar.first_data_date,
    (SELECT hour FROM hour_bucket)                       AS peak_hour,
    cm.avg_messages_per_conversation,
    cm.avg_conversation_duration_seconds,
    fb.conversations_with_feedback,
    sc.unique_sessions,
    cm.avg_first_response_seconds
FROM agent_rollup ar
CROSS JOIN conv_metrics cm
CROSS JOIN session_count sc
CROSS JOIN feedback fb
SQL;

        return DB::connection($connection)->selectOne($sql, [
            $agentId, $agentId, $agentId, $agentId, $agentId,
        ]) ?? (object) [
            'total_assistant' => 0, 'total_user' => 0, 'total_conversations' => 0,
            'avg_response_ms' => null, 'total_tokens' => 0, 'avg_tokens' => null,
            'positive_feedback' => 0, 'negative_feedback' => 0,
            'conversations_with_feedback' => 0, 'avg_messages_per_conversation' => null,
            'avg_conversation_duration_seconds' => null, 'avg_first_response_seconds' => null,
            'peak_hour' => null, 'first_data_date' => null, 'unique_sessions' => 0,
        ];
    }

    public function messagesByData(Carbon $startDate, Carbon $endDate)
    {
        $agentId = $this->getModel()->id;

        // Half-open range on the raw column — a ::date predicate can't use the index.
        return DB::connection(config('chatvia.analytics.connection'))
            ->table('analytics_agent_hourly_rollups')
            ->where('agent_id', $agentId)
            ->where('bucket_hour', '>=', $startDate->copy()->startOfDay())
            ->where('bucket_hour', '<', $endDate->copy()->startOfDay()->addDay())
            ->selectRaw('bucket_hour::date as date, SUM(assistant_messages) as count')
            ->groupBy('date')
            ->orderBy('date');
    }

    public function mostUsedModels()
    {
        $agentId = $this->getModel()->id;
        $sentinel = config('chatvia.analytics.unknown_llm_external_name');

        // Llm display metadata (descriptive_name, icon) is joined in PHP since
        // the llms table may live on a different connection from analytics.
        return DB::connection(config('chatvia.analytics.connection'))
            ->table('analytics_agent_hourly_rollups')
            ->where('agent_id', $agentId)
            ->where('llm_external_name', '!=', $sentinel)
            ->selectRaw(
                'llm_external_name as model_external_name,
                 SUM(assistant_messages) as usage_count,
                 0 as positive_feedback,
                 0 as negative_feedback,
                 ROUND(
                     (SUM(assistant_messages) * 100.0) / NULLIF((
                         SELECT SUM(assistant_messages)
                         FROM analytics_agent_hourly_rollups
                         WHERE agent_id = ? AND llm_external_name <> ?
                     ), 0),
                     1
                 ) as percentage',
                [$agentId, $sentinel]
            )
            ->groupBy('llm_external_name')
            ->havingRaw('SUM(assistant_messages) > 0')
            ->orderByRaw('SUM(assistant_messages) DESC');
    }

    public function dailyMessages(int $days)
    {
        $agentId = $this->getModel()->id;
        $startDate = now()->subDays($days)->startOfDay();

        return DB::connection(config('chatvia.analytics.connection'))
            ->table('analytics_agent_hourly_rollups')
            ->where('agent_id', $agentId)
            ->where('bucket_hour', '>=', $startDate)
            ->selectRaw('bucket_hour::date as date, SUM(assistant_messages) as messages')
            ->groupBy('date')
            ->orderBy('date');
    }

    public function mostAccessedFiles()
    {
        $agentId = $this->getModel()->id;

        return DB::table('agent_sources')
            ->join('source_files', function ($join): void {
                $join->on('source_files.id', '=', 'agent_sources.sourceable_id')
                    ->where('agent_sources.sourceable_type', '=', 'source_file');
            })
            ->where('agent_sources.agent_id', $agentId)
            ->select([
                'source_files.filename',
                'agent_sources.retrieval_count',
                'source_files.mime_type',
                'agent_sources.last_retrieved_at',
            ])
            ->where('agent_sources.retrieval_count', '>', 0)
            ->orderByDesc('agent_sources.retrieval_count');
    }

    /**
     * Knowledge sources this agent has retrieved, ranked by retrieval count —
     * read from the durable per-day analytics rollup (`analytics_agent_source_daily`),
     * NOT the live `agent_sources` pivot. The rollup is keyed by (type, id) with
     * no FK and isn't touched by source deletion, so a source that's since been
     * DELETED still shows in usage insights. It carries only (type, id); the
     * caller resolves display names from the live source tables (which is
     * connection-safe — the analytics rollup may live on a separate database).
     *
     * Returns rows: { sub_kind, sourceable_id, count, last_used_at }
     */
    public function mostAccessedSources()
    {
        $agentId = $this->getModel()->id;

        return DB::connection(config('chatvia.analytics.connection'))
            ->table('analytics_agent_source_daily')
            ->where('agent_id', $agentId)
            ->groupBy('sourceable_type', 'sourceable_id')
            ->select([
                'sourceable_type as sub_kind',
                'sourceable_id',
                DB::raw('SUM(retrieval_count) as count'),
                DB::raw('MAX(bucket_day) as last_used_at'),
                DB::raw('MAX(source_name) as name'),
                DB::raw('MAX(source_mime) as mime_type'),
            ])
            ->orderByRaw('SUM(retrieval_count) DESC');
    }

    /**
     * Account-level webhook functions this agent has actually called, ranked
     * by per-agent invocation count on the `agent_function` pivot.
     *
     * Returns: { name, description, count, last_used_at }
     */
    public function mostUsedFunctions()
    {
        $agentId = $this->getModel()->id;

        // Durable analytics rollup (not the live agent_function pivot) so a
        // deleted function still shows, with its label captured at call time.
        return DB::connection(config('chatvia.analytics.connection'))
            ->table('analytics_agent_function_daily')
            ->where('agent_id', $agentId)
            ->groupBy('function_id')
            ->select([
                DB::raw('MAX(function_name) as name'),
                DB::raw('MAX(function_description) as description'),
                DB::raw('SUM(invocation_count) as count'),
                DB::raw('MAX(bucket_day) as last_used_at'),
            ])
            ->orderByRaw('SUM(invocation_count) DESC');
    }

    /**
     * Individual MCP tools this agent has actually called, ranked by invocation
     * count from the durable analytics rollup, so the UI can show "tool name +
     * which server it belongs to" in one row.
     *
     * Returns: { tool_name, tool_description, server_name, server_slug,
     *            connector_slug, count, last_used_at }
     */
    public function mostUsedMcpTools()
    {
        $agentId = $this->getModel()->id;

        // Durable analytics rollup (keyed by slug+name, no FK) so a
        // deleted/re-synced tool still shows, with labels captured at call time.
        return DB::connection(config('chatvia.analytics.connection'))
            ->table('analytics_agent_mcp_tool_daily')
            ->where('agent_id', $agentId)
            ->groupBy('server_slug', 'tool_name')
            ->select([
                'tool_name',
                'server_slug',
                DB::raw('MAX(tool_description) as tool_description'),
                DB::raw('MAX(server_name) as server_name'),
                DB::raw('MAX(connector_slug) as connector_slug'),
                DB::raw('SUM(invocation_count) as count'),
                DB::raw('MAX(bucket_day) as last_used_at'),
            ])
            ->orderByRaw('SUM(invocation_count) DESC');
    }

    public function conversationsByCountry()
    {
        $agentId = $this->getModel()->id;
        $sentinel = config('chatvia.analytics.unknown_country_code');

        // Country name resolution happens in StatsResourceData::getCountryStats
        // because countries lives on the main DB and may not be reachable from
        // a separate analytics connection.
        return DB::connection(config('chatvia.analytics.connection'))
            ->table('analytics_agent_hourly_rollups')
            ->where('agent_id', $agentId)
            ->where('country_code', '!=', $sentinel)
            ->selectRaw(
                'country_code,
                 SUM(conversations_started) as conversation_count,
                 ROUND(
                     (SUM(conversations_started) * 100.0) / NULLIF((
                         SELECT SUM(conversations_started)
                         FROM analytics_agent_hourly_rollups
                         WHERE agent_id = ? AND country_code <> ?
                     ), 0),
                     1
                 ) as percentage',
                [$agentId, $sentinel]
            )
            ->groupBy('country_code')
            ->havingRaw('SUM(conversations_started) > 0')
            ->orderByRaw('SUM(conversations_started) DESC');
    }

    public function conversationsByHour()
    {
        $agentId = $this->getModel()->id;

        return DB::connection(config('chatvia.analytics.connection'))
            ->table('analytics_agent_hourly_rollups')
            ->where('agent_id', $agentId)
            ->selectRaw('EXTRACT(HOUR FROM bucket_hour)::int as hour, SUM(conversations_started) as conversation_count')
            ->groupBy('hour')
            ->orderBy('hour');
    }

    public function conversationsByDayOfWeek()
    {
        $agentId = $this->getModel()->id;

        return DB::connection(config('chatvia.analytics.connection'))
            ->table('analytics_agent_hourly_rollups')
            ->where('agent_id', $agentId)
            ->selectRaw('EXTRACT(DOW FROM bucket_hour)::int as day_of_week, SUM(conversations_started) as conversation_count')
            ->groupBy('day_of_week')
            ->orderBy('day_of_week');
    }

    public function conversationsTrend(int $days = 30)
    {
        $agentId = $this->getModel()->id;
        $startDate = now()->subDays($days)->startOfDay();

        return DB::connection(config('chatvia.analytics.connection'))
            ->table('analytics_agent_hourly_rollups')
            ->where('agent_id', $agentId)
            ->where('bucket_hour', '>=', $startDate)
            ->selectRaw('bucket_hour::date as date, SUM(conversations_started) as conversations')
            ->groupBy('date')
            ->orderBy('date');
    }

    /**
     * Get topics grouped by date within a date range.
     *
     * The rollup lives on the analytics connection while `topics` lives on the
     * default connection — they may be separate databases (DB_ANALYTICS_DATABASE).
     * So we aggregate by topic_id on the analytics side, then resolve names from
     * the default connection rather than cross-DB joining.
     */
    public function topicsByDateRange(Carbon $startDate, Carbon $endDate)
    {
        $agentId = $this->getModel()->id;

        $rows = DB::connection(config('chatvia.analytics.connection'))
            ->table('analytics_agent_topic_daily')
            ->where('agent_id', $agentId)
            ->whereRaw('bucket_day >= ?', [$startDate->format('Y-m-d')])
            ->whereRaw('bucket_day <= ?', [$endDate->format('Y-m-d')])
            ->groupBy('bucket_day', 'topic_id')
            ->select([
                DB::raw('bucket_day::text as date'),
                'topic_id',
                DB::raw('SUM(conversation_count) as count'),
            ])
            ->orderBy('date')
            ->get();

        $names = $this->resolveTopicNames($rows->pluck('topic_id'));

        return $rows
            ->map(function (object $row) use ($names): object {
                $row->topic_name = $names[$row->topic_id] ?? null;

                return $row;
            })
            ->sortBy([['date', 'asc'], ['topic_name', 'asc']])
            ->values();
    }

    /**
     * Get top topics with total counts within a date range. Names resolved from
     * the default connection — see {@see topicsByDateRange()} for the cross-DB note.
     */
    public function topTopics(Carbon $startDate, Carbon $endDate, int $limit = 10)
    {
        $agentId = $this->getModel()->id;

        $rows = DB::connection(config('chatvia.analytics.connection'))
            ->table('analytics_agent_topic_daily')
            ->where('agent_id', $agentId)
            ->whereRaw('bucket_day >= ?', [$startDate->format('Y-m-d')])
            ->whereRaw('bucket_day <= ?', [$endDate->format('Y-m-d')])
            ->groupBy('topic_id')
            ->select([
                'topic_id',
                DB::raw('SUM(conversation_count) as total_count'),
            ])
            ->orderByDesc('total_count')
            ->limit($limit)
            ->get();

        $names = $this->resolveTopicNames($rows->pluck('topic_id'));

        return $rows->map(function (object $row) use ($names): object {
            $row->topic_name = $names[$row->topic_id] ?? null;

            return $row;
        });
    }

    /**
     * Map topic ids to their names from the default connection.
     *
     * @param  Collection<int, int|string>  $topicIds
     * @return Collection<int, string>
     */
    private function resolveTopicNames($topicIds): Collection
    {
        if ($topicIds->isEmpty()) {
            return collect();
        }

        return DB::table('topics')
            ->whereIn('id', $topicIds->unique()->all())
            ->pluck('topic', 'id');
    }
}
