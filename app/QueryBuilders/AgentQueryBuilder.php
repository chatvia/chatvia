<?php

namespace App\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AgentQueryBuilder extends Builder
{
    public function countAssistantMessages()
    {
        $agentId = $this->getModel()->id;

        return DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->join('chat_messages', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->where('chat_sessions.agent_id', $agentId)
            ->where('chat_messages.role', 'assistant')
            ->count('chat_messages.id');
    }

    public function countConversations()
    {
        $agentId = $this->getModel()->id;

        return DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->where('chat_sessions.agent_id', $agentId)
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
            ->where('chat_messages.role', 'assistant')
            ->selectRaw('ROUND(AVG(chat_messages.response_time_ms) / 1000, 1) as avg_response_time')
            ->value('avg_response_time');
    }

    public function satisfactionRate()
    {
        $agentId = $this->getModel()->id;

        $result = DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->join('chat_messages', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->where('chat_sessions.agent_id', $agentId)
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
            ->where('chat_messages.role', 'assistant')
            ->whereNotNull('chat_messages.llm_id')
            ->selectRaw('
                llms.external_name as model_external_name,
                COUNT(*) as usage_count,
                COUNT(CASE WHEN chat_messages.feedback = ? THEN 1 END) as positive_feedback,
                COUNT(CASE WHEN chat_messages.feedback = ? THEN 1 END) as negative_feedback,
                ROUND(
                    (COUNT(*) * 100.0) / (
                        SELECT COUNT(*)
                        FROM chat_messages cm2
                        JOIN chat_conversations cc2 ON cm2.chat_conversation_id = cc2.id
                        JOIN chat_sessions cs2 ON cc2.chat_session_id = cs2.id
                        WHERE cs2.agent_id = ?
                        AND cm2.role = ?
                        AND cm2.llm_id IS NOT NULL
                    ),
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
            ->join('source_files', function ($join) {
                $join->on('source_files.id', '=', 'agent_sources.sourceable_id')
                    ->where('agent_sources.sourceable_type', '=', \App\Models\SourceFile::class);
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
            ->whereNotNull('chat_sessions.country_id')
            ->selectRaw('
                countries.code as country_code,
                countries.name as country_name,
                COUNT(DISTINCT chat_conversations.id) as conversation_count,
                ROUND(
                    (COUNT(DISTINCT chat_conversations.id) * 100.0) / (
                        SELECT COUNT(DISTINCT cc2.id)
                        FROM chat_conversations cc2
                        JOIN chat_sessions cs2 ON cc2.chat_session_id = cs2.id
                        WHERE cs2.agent_id = ?
                    ),
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
            ->selectRaw('ROUND(COUNT(chat_messages.id)::numeric / COUNT(DISTINCT chat_conversations.id), 1) as avg_messages')
            ->value('avg_messages') ?? 0;
    }

    public function conversationsByHour()
    {
        $agentId = $this->getModel()->id;

        return DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->where('chat_sessions.agent_id', $agentId)
            ->selectRaw('EXTRACT(HOUR FROM chat_conversations.created_at) as hour, COUNT(DISTINCT chat_conversations.id) as conversation_count')
            ->groupBy('hour')
            ->orderBy('hour');
    }

    public function peakActivityHour()
    {
        $agentId = $this->getModel()->id;

        $result = DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->where('chat_sessions.agent_id', $agentId)
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
            ->whereNotNull('chat_messages.feedback')
            ->distinct()
            ->count('chat_conversations.id');
    }

    public function avgTokensPerMessage()
    {
        $agentId = $this->getModel()->id;

        return DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->join('chat_messages', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->where('chat_sessions.agent_id', $agentId)
            ->where('chat_messages.role', 'assistant')
            ->whereNotNull('chat_messages.token_count')
            ->selectRaw('ROUND(AVG(chat_messages.token_count)) as avg_tokens')
            ->value('avg_tokens') ?? 0;
    }

    public function totalTokensUsed()
    {
        $agentId = $this->getModel()->id;

        return DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->join('chat_messages', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->where('chat_sessions.agent_id', $agentId)
            ->where('chat_messages.role', 'assistant')
            ->sum('chat_messages.token_count') ?? 0;
    }

    public function conversationsTrend(int $days = 30)
    {
        $agentId = $this->getModel()->id;
        $startDate = now()->subDays($days)->startOfDay();

        return DB::table('chat_sessions')
            ->join('chat_conversations', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->where('chat_sessions.agent_id', $agentId)
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
            ->selectRaw('MIN(DATE(chat_conversations.created_at)) as first_date')
            ->value('first_date');

        return $result ? Carbon::parse($result) : null;
    }
}
