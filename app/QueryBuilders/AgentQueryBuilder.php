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
                COUNT(*) as total_feedback,
                CASE
                    WHEN COUNT(*) = 0 THEN 0
                    ELSE ROUND(
                        (COUNT(CASE WHEN chat_messages.feedback = ? THEN 1 END) * 100.0) / COUNT(*),
                        1
                    )
                END as satisfaction_rate
            ', ['positive', 'negative', 'positive'])
            ->first();

        return $result->satisfaction_rate ?? 0;
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
            ->where('chat_sessions.agent_id', $agentId)
            ->where('chat_messages.role', 'assistant')
            ->whereNotNull('chat_messages.model')
            ->selectRaw('
                chat_messages.model as model_external_name,
                COUNT(*) as usage_count,
                ROUND(
                    (COUNT(*) * 100.0) / (
                        SELECT COUNT(*)
                        FROM chat_messages cm2
                        JOIN chat_conversations cc2 ON cm2.chat_conversation_id = cc2.id
                        JOIN chat_sessions cs2 ON cc2.chat_session_id = cs2.id
                        WHERE cs2.agent_id = ?
                        AND cm2.role = ?
                        AND cm2.model IS NOT NULL
                    ),
                    1
                ) as percentage
            ', [$agentId, 'assistant'])
            ->groupBy('chat_messages.model')
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

        return DB::table('agent_files')
            ->where('agent_files.agent_id', $agentId)
            ->select(['agent_files.filename', 'agent_files.retrieval_count', 'agent_files.mime_type'])
            ->where('agent_files.retrieval_count', '>', 0)
            ->orderByDesc('agent_files.retrieval_count');
    }
}
