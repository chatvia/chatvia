<?php

namespace Domain\Agent\QueryBuilders;

use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
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
     * Add total messages count as a subquery select.
     */
    public function withTotalMessages(): self
    {
        return $this->addSelect([
            'total_messages' => DB::table('chat_messages')
                ->join('chat_conversations', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
                ->join('chat_sessions', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
                ->whereColumn('chat_sessions.agent_id', 'agents.id')
                ->selectRaw('count(*)'),
        ]);
    }

    /**
     * Add unique users count as a subquery select.
     */
    public function withUniqueUsers(): self
    {
        return $this->addSelect([
            'unique_users' => DB::table('chat_sessions')
                ->whereColumn('chat_sessions.agent_id', 'agents.id')
                ->whereNotNull('chat_sessions.user_id')
                ->selectRaw('count(distinct chat_sessions.user_id)'),
        ]);
    }

    /**
     * Add all stats (total_members, total_messages, unique_users) as subquery selects.
     */
    public function withStats(): self
    {
        return $this
            ->withTotalMembers()
            ->withTotalMessages()
            ->withUniqueUsers();
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
        $connection = config('analytics.connection');

        $sql = <<<'SQL'
WITH agent_rollup AS (
    SELECT
        COALESCE(SUM(user_messages), 0)             AS total_user,
        COALESCE(SUM(assistant_messages), 0)        AS total_assistant,
        COALESCE(SUM(conversations_started), 0)     AS total_conversations,
        COALESCE(SUM(positive_feedback), 0)         AS positive_feedback,
        COALESCE(SUM(negative_feedback), 0)         AS negative_feedback,
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
conv_metrics AS (
    SELECT
        AVG(message_count)                                    AS avg_messages_per_conversation,
        AVG(NULLIF(duration_seconds, 0))                      AS avg_conversation_duration_seconds,
        COUNT(*) FILTER (WHERE had_feedback)                  AS conversations_with_feedback
    FROM analytics_conversation_metrics
    WHERE agent_id = ?
),
session_count AS (
    SELECT COUNT(*) AS unique_sessions
    FROM analytics_session_firsts
    WHERE agent_id = ?
),
-- First-response time uses the granular event log to get first user / first
-- assistant message per conversation; rollup buckets are too coarse for this.
first_response AS (
    SELECT AVG(EXTRACT(EPOCH FROM (a.occurred_at - u.occurred_at))) AS avg_first_response_seconds
    FROM (
        SELECT conversation_external_id, MIN(occurred_at) AS occurred_at
        FROM analytics_events
        WHERE agent_id = ? AND event_type = 'message_sent' AND role = 'user'
        GROUP BY conversation_external_id
    ) u
    JOIN (
        SELECT conversation_external_id, MIN(occurred_at) AS occurred_at
        FROM analytics_events
        WHERE agent_id = ? AND event_type = 'message_sent' AND role = 'assistant'
        GROUP BY conversation_external_id
    ) a ON a.conversation_external_id = u.conversation_external_id
    WHERE a.occurred_at > u.occurred_at
)
SELECT
    ar.total_assistant,
    ar.total_user,
    ar.total_conversations,
    ar.avg_response_ms,
    ar.total_tokens,
    ar.avg_tokens,
    ar.positive_feedback,
    ar.negative_feedback,
    ar.first_data_date,
    (SELECT hour FROM hour_bucket)                       AS peak_hour,
    cm.avg_messages_per_conversation,
    cm.avg_conversation_duration_seconds,
    cm.conversations_with_feedback,
    sc.unique_sessions,
    fr.avg_first_response_seconds
FROM agent_rollup ar
CROSS JOIN conv_metrics cm
CROSS JOIN session_count sc
CROSS JOIN first_response fr
SQL;

        return DB::connection($connection)->selectOne($sql, [
            $agentId, $agentId, $agentId, $agentId, $agentId, $agentId,
        ]) ?? (object) [
            'total_assistant' => 0, 'total_user' => 0, 'total_conversations' => 0,
            'avg_response_ms' => null, 'total_tokens' => 0, 'avg_tokens' => null,
            'positive_feedback' => 0, 'negative_feedback' => 0,
            'conversations_with_feedback' => 0, 'avg_messages_per_conversation' => null,
            'avg_conversation_duration_seconds' => null, 'avg_first_response_seconds' => null,
            'peak_hour' => null, 'first_data_date' => null, 'unique_sessions' => 0,
        ];
    }

    public function countAssistantMessages()
    {
        $agentId = $this->getModel()->id;

        return DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->join('chat_messages', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->where('chat_sessions.agent_id', $agentId)
            ->where('chat_conversations.is_visible', true)
            ->where('chat_messages.role', 'assistant')
            ->count('chat_messages.id');
    }

    public function countConversations()
    {
        $agentId = $this->getModel()->id;

        return DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->where('chat_sessions.agent_id', $agentId)
            ->where('chat_conversations.is_visible', true)
            ->distinct()
            ->count('chat_conversations.id');
    }

    public function avgResponseTime()
    {
        $agentId = $this->getModel()->id;

        return DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->join('chat_messages', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->where('chat_sessions.agent_id', $agentId)
            ->where('chat_conversations.is_visible', true)
            ->where('chat_messages.role', 'assistant')
            ->selectRaw('ROUND(AVG(chat_messages.response_time_ms) / 1000, 1) as avg_response_time')
            ->value('avg_response_time');
    }

    public function satisfactionRate(): array
    {
        $agentId = $this->getModel()->id;

        $result = DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->join('chat_messages', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->where('chat_sessions.agent_id', $agentId)
            ->where('chat_conversations.is_visible', true)
            ->where('chat_messages.role', 'assistant')
            ->whereNotNull('chat_messages.feedback')
            ->selectRaw('
                COUNT(CASE WHEN chat_messages.feedback = ? THEN 1 END) as positive_count,
                COUNT(CASE WHEN chat_messages.feedback = ? THEN 1 END) as negative_count,
                COUNT(*) as total_feedback
            ', ['positive', 'negative'])
            ->first();

        return [
            'positive' => $result->positive_count ?? 0,
            'negative' => $result->negative_count ?? 0,
            'total' => $result->total_feedback ?? 0,
        ];
    }

    public function messagesByData(Carbon $startDate, Carbon $endDate)
    {
        $agentId = $this->getModel()->id;

        return DB::connection(config('analytics.connection'))
            ->table('analytics_agent_hourly_rollups')
            ->where('agent_id', $agentId)
            ->whereRaw('bucket_hour::date BETWEEN ? AND ?', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->selectRaw('bucket_hour::date as date, SUM(assistant_messages) as count')
            ->groupBy('date')
            ->orderBy('date');
    }

    public function mostUsedModels()
    {
        $agentId = $this->getModel()->id;
        $sentinel = config('analytics.unknown_llm_external_name');

        // Llm display metadata (descriptive_name, icon) is joined in PHP since
        // the llms table may live on a different connection from analytics.
        return DB::connection(config('analytics.connection'))
            ->table('analytics_agent_hourly_rollups')
            ->where('agent_id', $agentId)
            ->where('llm_external_name', '!=', $sentinel)
            ->selectRaw(
                'llm_external_name as model_external_name,
                 SUM(assistant_messages) as usage_count,
                 SUM(positive_feedback) as positive_feedback,
                 SUM(negative_feedback) as negative_feedback,
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

        return DB::connection(config('analytics.connection'))
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
     * Knowledge sources (files, websites, text snippets, YouTube videos)
     * ranked by this agent's per-source retrieval count. The polymorphic
     * `agent_sources` pivot already aggregates retrievals across all source
     * types — we LEFT JOIN each underlying table to surface a display name
     * and pick the best one in PHP afterwards.
     *
     * Returns: { kind: 'knowledge', sub_kind, name, count, last_used_at }
     */
    public function mostAccessedSources()
    {
        $agentId = $this->getModel()->id;

        return DB::table('agent_sources')
            ->leftJoin('source_files', function ($join): void {
                $join->on('source_files.id', '=', 'agent_sources.sourceable_id')
                    ->where('agent_sources.sourceable_type', '=', 'source_file');
            })
            ->leftJoin('source_websites', function ($join): void {
                $join->on('source_websites.id', '=', 'agent_sources.sourceable_id')
                    ->where('agent_sources.sourceable_type', '=', 'source_website');
            })
            ->leftJoin('source_texts', function ($join): void {
                $join->on('source_texts.id', '=', 'agent_sources.sourceable_id')
                    ->where('agent_sources.sourceable_type', '=', 'source_text');
            })
            ->leftJoin('source_youtube_videos', function ($join): void {
                $join->on('source_youtube_videos.id', '=', 'agent_sources.sourceable_id')
                    ->where('agent_sources.sourceable_type', '=', 'source_youtube_video');
            })
            ->leftJoin('source_website_urls', function ($join): void {
                $join->on('source_website_urls.id', '=', 'agent_sources.sourceable_id')
                    ->where('agent_sources.sourceable_type', '=', 'agent_website_url');
            })
            ->where('agent_sources.agent_id', $agentId)
            ->where('agent_sources.retrieval_count', '>', 0)
            ->select([
                'agent_sources.sourceable_type as sub_kind',
                'source_files.filename as file_name',
                'source_files.mime_type as file_mime_type',
                'source_websites.url as website_url',
                'source_texts.excerpt as text_excerpt',
                'source_youtube_videos.title as youtube_title',
                'source_youtube_videos.url as youtube_url',
                'source_website_urls.url as website_url_url',
                'agent_sources.retrieval_count as count',
                'agent_sources.last_retrieved_at as last_used_at',
            ])
            ->orderByDesc('agent_sources.retrieval_count');
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

        return DB::table('agent_function')
            ->join('functions', 'functions.id', '=', 'agent_function.function_id')
            ->where('agent_function.agent_id', $agentId)
            ->where('agent_function.invocation_count', '>', 0)
            ->select([
                'functions.name',
                'functions.description',
                'agent_function.invocation_count as count',
                'agent_function.last_invoked_at as last_used_at',
            ])
            ->orderByDesc('agent_function.invocation_count');
    }

    /**
     * Individual MCP tools this agent has actually called, ranked by
     * per-(agent, tool) invocation count on the `agent_mcp_tool` pivot.
     * Joined to `mcp_servers` so the UI can show "tool name + which
     * server it belongs to" in one row.
     *
     * Returns: { tool_name, tool_description, server_name, server_slug,
     *            connector_slug, count, last_used_at }
     */
    public function mostUsedMcpTools()
    {
        $agentId = $this->getModel()->id;

        return DB::table('agent_mcp_tool')
            ->join('mcp_tools', 'mcp_tools.id', '=', 'agent_mcp_tool.mcp_tool_id')
            ->join('mcp_servers', 'mcp_servers.id', '=', 'mcp_tools.mcp_server_id')
            ->where('agent_mcp_tool.agent_id', $agentId)
            ->where('agent_mcp_tool.invocation_count', '>', 0)
            ->select([
                'mcp_tools.name as tool_name',
                'mcp_tools.description as tool_description',
                'mcp_servers.name as server_name',
                'mcp_servers.slug as server_slug',
                'mcp_servers.connector_slug',
                'agent_mcp_tool.invocation_count as count',
                'agent_mcp_tool.last_invoked_at as last_used_at',
            ])
            ->orderByDesc('agent_mcp_tool.invocation_count');
    }

    public function conversationsByCountry()
    {
        $agentId = $this->getModel()->id;
        $sentinel = config('analytics.unknown_country_code');

        // Country name resolution happens in StatsResourceData::getCountryStats
        // because countries lives on the main DB and may not be reachable from
        // a separate analytics connection.
        return DB::connection(config('analytics.connection'))
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

    public function avgMessagesPerConversation()
    {
        $agentId = $this->getModel()->id;

        return DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->join('chat_messages', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->where('chat_sessions.agent_id', $agentId)
            ->where('chat_conversations.is_visible', true)
            ->selectRaw('ROUND(COUNT(chat_messages.id)::numeric / NULLIF(COUNT(DISTINCT chat_conversations.id), 0), 1) as avg_messages')
            ->value('avg_messages') ?? 0;
    }

    public function conversationsByHour()
    {
        $agentId = $this->getModel()->id;

        return DB::connection(config('analytics.connection'))
            ->table('analytics_agent_hourly_rollups')
            ->where('agent_id', $agentId)
            ->selectRaw('EXTRACT(HOUR FROM bucket_hour)::int as hour, SUM(conversations_started) as conversation_count')
            ->groupBy('hour')
            ->orderBy('hour');
    }

    public function peakActivityHour(): ?int
    {
        $agentId = $this->getModel()->id;

        $result = DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->where('chat_sessions.agent_id', $agentId)
            ->where('chat_conversations.is_visible', true)
            ->selectRaw('EXTRACT(HOUR FROM chat_conversations.created_at) as hour, COUNT(DISTINCT chat_conversations.id) as conversation_count')
            ->groupBy('hour')
            ->orderByDesc('conversation_count')
            ->first();

        return $result ? (int) $result->hour : null;
    }

    public function conversationsByDayOfWeek()
    {
        $agentId = $this->getModel()->id;

        return DB::connection(config('analytics.connection'))
            ->table('analytics_agent_hourly_rollups')
            ->where('agent_id', $agentId)
            ->selectRaw('EXTRACT(DOW FROM bucket_hour)::int as day_of_week, SUM(conversations_started) as conversation_count')
            ->groupBy('day_of_week')
            ->orderBy('day_of_week');
    }

    public function avgConversationDuration()
    {
        $agentId = $this->getModel()->id;

        $durations = DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->join('chat_messages', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->where('chat_sessions.agent_id', $agentId)
            ->where('chat_conversations.is_visible', true)
            ->selectRaw('
                chat_conversations.id,
                EXTRACT(EPOCH FROM (
                    MAX(chat_messages.created_at) - MIN(chat_messages.created_at)
                )) as duration_seconds
            ')
            ->groupBy('chat_conversations.id')
            ->get();

        return $durations->avg('duration_seconds');
    }

    public function totalUserMessages()
    {
        $agentId = $this->getModel()->id;

        return DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->join('chat_messages', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->where('chat_sessions.agent_id', $agentId)
            ->where('chat_conversations.is_visible', true)
            ->where('chat_messages.role', 'user')
            ->count('chat_messages.id');
    }

    public function conversationsWithFeedback()
    {
        $agentId = $this->getModel()->id;

        return DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->join('chat_messages', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->where('chat_sessions.agent_id', $agentId)
            ->where('chat_conversations.is_visible', true)
            ->whereNotNull('chat_messages.feedback')
            ->distinct()
            ->count('chat_conversations.id');
    }

    public function conversationsTrend(int $days = 30)
    {
        $agentId = $this->getModel()->id;
        $startDate = now()->subDays($days)->startOfDay();

        return DB::connection(config('analytics.connection'))
            ->table('analytics_agent_hourly_rollups')
            ->where('agent_id', $agentId)
            ->where('bucket_hour', '>=', $startDate)
            ->selectRaw('bucket_hour::date as date, SUM(conversations_started) as conversations')
            ->groupBy('date')
            ->orderBy('date');
    }

    public function uniqueSessionsCount()
    {
        $agentId = $this->getModel()->id;

        return DB::table('chat_sessions')
            ->where('agent_id', $agentId)
            ->count('id');
    }

    public function avgFirstResponseTime()
    {
        $agentId = $this->getModel()->id;

        // Get the first user message and first assistant response for each conversation
        return DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->join('chat_messages as user_msg', 'user_msg.chat_conversation_id', '=', 'chat_conversations.id')
            ->join('chat_messages as assistant_msg', 'assistant_msg.chat_conversation_id', '=', 'chat_conversations.id')
            ->where('chat_sessions.agent_id', $agentId)
            ->where('chat_conversations.is_visible', true)
            ->where('user_msg.role', 'user')
            ->where('assistant_msg.role', 'assistant')
            ->where('assistant_msg.created_at', '>', DB::raw('user_msg.created_at'))
            ->selectRaw('
                AVG(
                    EXTRACT(EPOCH FROM (assistant_msg.created_at - user_msg.created_at))
                ) as avg_first_response_seconds
            ')
            ->value('avg_first_response_seconds');
    }

    public function firstDataDate(): ?Carbon
    {
        $agentId = $this->getModel()->id;

        $result = DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->where('chat_sessions.agent_id', $agentId)
            ->where('chat_conversations.is_visible', true)
            ->selectRaw('MIN(DATE(chat_conversations.created_at)) as first_date')
            ->value('first_date');

        return $result ? Carbon::parse($result) : null;
    }

    /**
     * Get topics grouped by date within a date range.
     */
    public function topicsByDateRange(Carbon $startDate, Carbon $endDate)
    {
        $agentId = $this->getModel()->id;

        return DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->join('chat_topics', 'chat_topics.id', '=', 'chat_conversations.chat_topic_id')
            ->where('chat_sessions.agent_id', $agentId)
            ->where('chat_conversations.is_visible', true)
            ->whereNotNull('chat_conversations.chat_topic_id')
            ->whereRaw('DATE(chat_conversations.created_at) >= ?', [$startDate->format('Y-m-d')])
            ->whereRaw('DATE(chat_conversations.created_at) <= ?', [$endDate->format('Y-m-d')])
            ->groupBy('date', 'chat_topics.id', 'chat_topics.topic')
            ->select([
                DB::raw('DATE(chat_conversations.created_at) as date'),
                'chat_topics.id as topic_id',
                'chat_topics.topic as topic_name',
                DB::raw('COUNT(chat_conversations.id) as count'),
            ])
            ->orderBy('date')
            ->orderBy('topic_name')
            ->get();
    }

    /**
     * Get top topics with total counts within a date range.
     */
    public function topTopics(Carbon $startDate, Carbon $endDate, int $limit = 10)
    {
        $agentId = $this->getModel()->id;

        return DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->join('chat_topics', 'chat_topics.id', '=', 'chat_conversations.chat_topic_id')
            ->where('chat_sessions.agent_id', $agentId)
            ->where('chat_conversations.is_visible', true)
            ->whereNotNull('chat_conversations.chat_topic_id')
            ->whereRaw('DATE(chat_conversations.created_at) >= ?', [$startDate->format('Y-m-d')])
            ->whereRaw('DATE(chat_conversations.created_at) <= ?', [$endDate->format('Y-m-d')])
            ->groupBy('chat_topics.id', 'chat_topics.topic')
            ->select([
                'chat_topics.id as topic_id',
                'chat_topics.topic as topic_name',
                DB::raw('COUNT(chat_conversations.id) as total_count'),
            ])
            ->orderByDesc('total_count')
            ->limit($limit)
            ->get();
    }

    /**
     * Get the average tokens per message (prompt_tokens + completion_tokens).
     */
    public function avgTokensPerMessage(): ?int
    {
        $agentId = $this->getModel()->id;

        $result = DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->join('chat_messages', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->where('chat_sessions.agent_id', $agentId)
            ->where('chat_conversations.is_visible', true)
            ->where('chat_messages.role', 'assistant')
            ->selectRaw('ROUND(AVG(COALESCE(chat_messages.prompt_tokens, 0) + COALESCE(chat_messages.completion_tokens, 0))) as avg_tokens')
            ->value('avg_tokens');

        return $result ? (int) $result : null;
    }

    /**
     * Get the total tokens used (sum of prompt_tokens + completion_tokens).
     */
    public function totalTokensUsed(): ?int
    {
        $agentId = $this->getModel()->id;

        $result = DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->join('chat_messages', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->where('chat_sessions.agent_id', $agentId)
            ->where('chat_conversations.is_visible', true)
            ->where('chat_messages.role', 'assistant')
            ->selectRaw('SUM(COALESCE(chat_messages.prompt_tokens, 0) + COALESCE(chat_messages.completion_tokens, 0)) as total_tokens')
            ->value('total_tokens');

        return $result ? (int) $result : null;
    }
}
