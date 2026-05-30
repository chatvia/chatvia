<?php

namespace Domain\Agents\QueryBuilders;

use Domain\Agents\Models\Agent;
use Domain\Sources\Files\Models\SourceFile;
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
     * Single-pass aggregate over visible messages of this agent. Replaces ~12
     * separately-issued count/avg queries that all rejoined the same chain.
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

        $sql = <<<'SQL'
WITH conv AS (
    SELECT cc.id, cc.created_at
    FROM chat_conversations cc
    JOIN chat_sessions cs ON cs.id = cc.chat_session_id
    WHERE cs.agent_id = ?
      AND cc.visible = true
      AND cc.deleted_at IS NULL
),
msg AS (
    SELECT cm.id, cm.chat_conversation_id, cm.role, cm.feedback,
           cm.response_time_ms, cm.prompt_tokens, cm.completion_tokens, cm.created_at
    FROM chat_messages cm
    JOIN conv ON conv.id = cm.chat_conversation_id
),
per_conv AS (
    SELECT chat_conversation_id,
           COUNT(*) AS msg_count,
           EXTRACT(EPOCH FROM (MAX(created_at) - MIN(created_at))) AS duration_seconds,
           MIN(CASE WHEN role = 'user'      THEN created_at END) AS first_user_at,
           MIN(CASE WHEN role = 'assistant' THEN created_at END) AS first_assistant_at
    FROM msg
    GROUP BY chat_conversation_id
),
hour_bucket AS (
    SELECT EXTRACT(HOUR FROM created_at)::int AS hour, COUNT(*) AS c
    FROM conv
    GROUP BY 1
    ORDER BY c DESC
    LIMIT 1
),
session_counts AS (
    SELECT COUNT(*) AS unique_sessions
    FROM chat_sessions
    WHERE agent_id = ?
)
SELECT
    COUNT(*) FILTER (WHERE m.role = 'assistant')                                       AS total_assistant,
    COUNT(*) FILTER (WHERE m.role = 'user')                                            AS total_user,
    COUNT(DISTINCT m.chat_conversation_id)                                             AS total_conversations,
    AVG(m.response_time_ms) FILTER (WHERE m.role = 'assistant')                        AS avg_response_ms,
    SUM(COALESCE(m.prompt_tokens, 0) + COALESCE(m.completion_tokens, 0))
        FILTER (WHERE m.role = 'assistant')                                            AS total_tokens,
    AVG(COALESCE(m.prompt_tokens, 0) + COALESCE(m.completion_tokens, 0))
        FILTER (WHERE m.role = 'assistant')                                            AS avg_tokens,
    COUNT(*) FILTER (WHERE m.feedback = 'positive' AND m.role = 'assistant')           AS positive_feedback,
    COUNT(*) FILTER (WHERE m.feedback = 'negative' AND m.role = 'assistant')           AS negative_feedback,
    COUNT(DISTINCT m.chat_conversation_id)
        FILTER (WHERE m.feedback IS NOT NULL)                                          AS conversations_with_feedback,
    (SELECT AVG(msg_count) FROM per_conv)                                              AS avg_messages_per_conversation,
    (SELECT AVG(duration_seconds) FROM per_conv WHERE duration_seconds > 0)            AS avg_conversation_duration_seconds,
    (SELECT AVG(EXTRACT(EPOCH FROM (first_assistant_at - first_user_at)))
        FROM per_conv WHERE first_user_at IS NOT NULL AND first_assistant_at > first_user_at) AS avg_first_response_seconds,
    (SELECT hour FROM hour_bucket)                                                     AS peak_hour,
    (SELECT MIN(created_at)::date FROM conv)                                           AS first_data_date,
    (SELECT unique_sessions FROM session_counts)                                       AS unique_sessions
FROM msg m
SQL;

        return DB::selectOne($sql, [$agentId, $agentId]) ?? (object) [
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
            ->where('chat_conversations.visible', true)
            ->where('chat_messages.role', 'assistant')
            ->count('chat_messages.id');
    }

    public function countConversations()
    {
        $agentId = $this->getModel()->id;

        return DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->where('chat_sessions.agent_id', $agentId)
            ->where('chat_conversations.visible', true)
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
            ->where('chat_conversations.visible', true)
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
            ->where('chat_conversations.visible', true)
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

        return DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->join('chat_messages', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->where('chat_sessions.agent_id', $agentId)
            ->where('chat_conversations.visible', true)
            ->where('chat_messages.role', 'assistant')
            ->whereRaw('DATE(chat_messages.created_at) >= ?', [$startDate->format('Y-m-d')])
            ->whereRaw('DATE(chat_messages.created_at) <= ?', [$endDate->format('Y-m-d')])
            ->selectRaw('DATE(chat_messages.created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date');
    }

    public function mostUsedModels()
    {
        $agentId = $this->getModel()->id;

        return DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->join('chat_messages', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->join('llms', 'llms.id', '=', 'chat_messages.llm_id')
            ->where('chat_sessions.agent_id', $agentId)
            ->where('chat_conversations.visible', true)
            ->where('chat_messages.role', 'assistant')
            ->whereNotNull('chat_messages.llm_id')
            ->selectRaw('
                llms.external_name as model_external_name,
                COUNT(*) as usage_count,
                COUNT(CASE WHEN chat_messages.feedback = ? THEN 1 END) as positive_feedback,
                COUNT(CASE WHEN chat_messages.feedback = ? THEN 1 END) as negative_feedback,
                ROUND(
                    (COUNT(*) * 100.0) / NULLIF((
                        SELECT COUNT(*)
                        FROM chat_messages cm2
                        JOIN chat_conversations cc2 ON cm2.chat_conversation_id = cc2.id
                        JOIN chat_sessions cs2 ON cc2.chat_session_id = cs2.id
                        WHERE cs2.agent_id = ?
                        AND cc2.visible = true
                        AND cm2.role = ?
                        AND cm2.llm_id IS NOT NULL
                    ), 0),
                    1
                ) as percentage
            ', ['positive', 'negative', $agentId, 'assistant'])
            ->groupBy('llms.external_name')
            ->orderByDesc('usage_count');
    }

    public function dailyMessages(int $days)
    {
        $agentId = $this->getModel()->id;
        $startDate = now()->subDays($days)->startOfDay();

        return DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->join('chat_messages', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->where('chat_sessions.agent_id', $agentId)
            ->where('chat_conversations.visible', true)
            ->where('chat_messages.role', 'assistant')
            ->where('chat_messages.created_at', '>=', $startDate)
            ->selectRaw('DATE(chat_messages.created_at) as date, COUNT(*) as messages')
            ->groupBy('date')
            ->orderBy('date');
    }

    public function mostAccessedFiles()
    {
        $agentId = $this->getModel()->id;

        return DB::table('agent_sources')
            ->join('source_files', function ($join): void {
                $join->on('source_files.id', '=', 'agent_sources.sourceable_id')
                    ->where('agent_sources.sourceable_type', '=', SourceFile::class);
            })
            ->where('agent_sources.agent_id', $agentId)
            ->select([
                'source_files.filename',
                'source_files.retrieval_count',
                'source_files.mime_type',
                'agent_sources.last_retrieved_at',
            ])
            ->where('source_files.retrieval_count', '>', 0)
            ->orderByDesc('source_files.retrieval_count');
    }

    public function conversationsByCountry()
    {
        $agentId = $this->getModel()->id;

        return DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->join('countries', 'countries.id', '=', 'chat_sessions.country_id')
            ->where('chat_sessions.agent_id', $agentId)
            ->where('chat_conversations.visible', true)
            ->whereNotNull('chat_sessions.country_id')
            ->selectRaw('
                countries.code as country_code,
                countries.name as country_name,
                COUNT(DISTINCT chat_conversations.id) as conversation_count,
                ROUND(
                    (COUNT(DISTINCT chat_conversations.id) * 100.0) / NULLIF((
                        SELECT COUNT(DISTINCT cc2.id)
                        FROM chat_conversations cc2
                        JOIN chat_sessions cs2 ON cc2.chat_session_id = cs2.id
                        WHERE cs2.agent_id = ?
                        AND cc2.visible = true
                    ), 0),
                    1
                ) as percentage
            ', [$agentId])
            ->groupBy('countries.code', 'countries.name')
            ->orderByDesc('conversation_count');
    }

    public function avgMessagesPerConversation()
    {
        $agentId = $this->getModel()->id;

        return DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->join('chat_messages', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->where('chat_sessions.agent_id', $agentId)
            ->where('chat_conversations.visible', true)
            ->selectRaw('ROUND(COUNT(chat_messages.id)::numeric / NULLIF(COUNT(DISTINCT chat_conversations.id), 0), 1) as avg_messages')
            ->value('avg_messages') ?? 0;
    }

    public function conversationsByHour()
    {
        $agentId = $this->getModel()->id;

        return DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->where('chat_sessions.agent_id', $agentId)
            ->where('chat_conversations.visible', true)
            ->selectRaw('EXTRACT(HOUR FROM chat_conversations.created_at) as hour, COUNT(DISTINCT chat_conversations.id) as conversation_count')
            ->groupBy('hour')
            ->orderBy('hour');
    }

    public function peakActivityHour(): ?int
    {
        $agentId = $this->getModel()->id;

        $result = DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->where('chat_sessions.agent_id', $agentId)
            ->where('chat_conversations.visible', true)
            ->selectRaw('EXTRACT(HOUR FROM chat_conversations.created_at) as hour, COUNT(DISTINCT chat_conversations.id) as conversation_count')
            ->groupBy('hour')
            ->orderByDesc('conversation_count')
            ->first();

        return $result ? (int) $result->hour : null;
    }

    public function conversationsByDayOfWeek()
    {
        $agentId = $this->getModel()->id;

        return DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->where('chat_sessions.agent_id', $agentId)
            ->where('chat_conversations.visible', true)
            ->selectRaw('
                EXTRACT(DOW FROM chat_conversations.created_at) as day_of_week,
                COUNT(DISTINCT chat_conversations.id) as conversation_count
            ')
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
            ->where('chat_conversations.visible', true)
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
            ->where('chat_conversations.visible', true)
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
            ->where('chat_conversations.visible', true)
            ->whereNotNull('chat_messages.feedback')
            ->distinct()
            ->count('chat_conversations.id');
    }

    public function conversationsTrend(int $days = 30)
    {
        $agentId = $this->getModel()->id;
        $startDate = now()->subDays($days)->startOfDay();

        return DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->where('chat_sessions.agent_id', $agentId)
            ->where('chat_conversations.visible', true)
            ->where('chat_conversations.created_at', '>=', $startDate)
            ->selectRaw('DATE(chat_conversations.created_at) as date, COUNT(DISTINCT chat_conversations.id) as conversations')
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
            ->where('chat_conversations.visible', true)
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
            ->where('chat_conversations.visible', true)
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
            ->where('chat_conversations.visible', true)
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
            ->where('chat_conversations.visible', true)
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
            ->where('chat_conversations.visible', true)
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
            ->where('chat_conversations.visible', true)
            ->where('chat_messages.role', 'assistant')
            ->selectRaw('SUM(COALESCE(chat_messages.prompt_tokens, 0) + COALESCE(chat_messages.completion_tokens, 0)) as total_tokens')
            ->value('total_tokens');

        return $result ? (int) $result : null;
    }
}
