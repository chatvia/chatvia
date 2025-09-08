<?php

namespace App\Support\Agents;

use App\Data\Agent\StatsData;
use App\Models\Agent;
use App\Models\AgentFile;
use App\Models\ChatMessage;
use App\Support\Llms;

class Stats
{
    public Agent $agent;

    public function setAgent(Agent $agent): static
    {
        $this->agent = $agent;

        return $this;
    }

    public function getTotalMessages(): int
    {
        return ChatMessage::query()
            ->join('chat_conversations', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->join('chat_sessions', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->where('chat_sessions.agent_id', $this->agent->id)
            ->where('chat_messages.role', 'assistant')
            ->count();
    }

    public function getTotalMembers(): int
    {
        return $this->agent->members()->count();
    }

    public function averageResponseTime()
    {
        return ChatMessage::query()
            ->join('chat_conversations', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->join('chat_sessions', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->where('chat_sessions.agent_id', $this->agent->id)
            ->where('chat_messages.role', 'assistant')
            ->selectRaw('ROUND(AVG(chat_messages.response_time_ms) / 1000, 1) as avg_response_time')
            ->value('avg_response_time');
    }

    public function satisfactionRate()
    {
        $result = ChatMessage::query()
            ->join('chat_conversations', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->join('chat_sessions', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->where('chat_sessions.agent_id', $this->agent->id)
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

    public function getMessagesLastWeek(): array
    {
        $startDate = now()->subDays(6)->startOfDay();
        $endDate = now()->endOfDay();

        // Get daily message counts for the last 7 days
        $dailyMessages = ChatMessage::query()
            ->join('chat_conversations', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->join('chat_sessions', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->where('chat_sessions.agent_id', $this->agent->id)
            ->where('chat_messages.role', 'assistant')
            ->whereRaw('DATE(chat_messages.created_at) >= ?', [$startDate->format('Y-m-d')])
            ->whereRaw('DATE(chat_messages.created_at) <= ?', [$endDate->format('Y-m-d')])
            ->selectRaw('DATE(chat_messages.created_at) as date, COUNT(*) as count')
            ->groupBy('date')
            ->orderBy('date')
            ->pluck('count', 'date')
            ->toArray();

        // Generate last 7 days with day names, ensuring all days are present
        $weeklyData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dateKey = $date->format('Y-m-d');
            $dayName = $date->format('D'); // Mon, Tue, Wed, etc.

            $weeklyData[] = [
                'day' => $dayName,
                'date' => $dateKey,
                'value' => $dailyMessages[$dateKey] ?? 0,
            ];
        }

        return $weeklyData;
    }

    public function getMostUsedModels()
    {
        return ChatMessage::query()
            ->join('chat_conversations', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->join('chat_sessions', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->where('chat_sessions.agent_id', $this->agent->id)
            ->where('chat_messages.role', 'assistant')
            ->whereNotNull('chat_messages.model')
            ->selectRaw('
                chat_messages.model,
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
            ', [$this->agent->id, 'assistant'])
            ->groupBy('chat_messages.model')
            ->orderByDesc('usage_count')
            ->get()
            ->map(function ($item) {
                $model = app(Llms::class)->getByExternalName($item->model);

                return [
                    'name' => $model->name,
                    'icon' => $model->icon,
                    'count' => $item->usage_count,
                    'percentage' => $item->percentage,
                ];
            });
    }

    public function all(): StatsData
    {
        return StatsData::from([
            'totalMessages' => $this->getTotalMessages(),
            'totalMembers' => $this->getTotalMembers(),
            'avgResponseTime' => $this->averageResponseTime(),
            'satisfactionRate' => $this->satisfactionRate(),
            'messagesLastWeek' => $this->getMessagesLastWeek(),
            'modelUsage' => $this->getMostUsedModels()->toArray(),
            'dailyMessages' => $this->getDailyMessages()->toArray(),
            'topKnowledge' => $this->getTopAccessedFiles(),
            'lastUpdated' => now(),
        ]);
    }

    public function getDailyMessages(int $days = 30)
    {
        $startDate = now()->subDays($days)->startOfDay();

        return ChatMessage::query()
            ->join('chat_conversations', 'chat_messages.chat_conversation_id', '=', 'chat_conversations.id')
            ->join('chat_sessions', 'chat_conversations.chat_session_id', '=', 'chat_sessions.id')
            ->where('chat_sessions.agent_id', $this->agent->id)
            ->where('chat_messages.role', 'assistant')
            ->where('chat_messages.created_at', '>=', $startDate)
            ->selectRaw('DATE(chat_messages.created_at) as date, COUNT(*) as messages')
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'messages' => $item->messages,
                ];
            });
    }

    public function getTopAccessedFiles(int $limit = 5): array
    {
        return AgentFile::query()
            ->select(['filename', 'agent_id', 'retrieval_count', 'mime_type'])
            ->where('agent_id', $this->agent->id)
            ->where('retrieval_count', '>', 0)
            ->orderByDesc('retrieval_count')
            ->limit($limit)
            ->get()
            ->map(function ($file) {
                return [
                    'name' => $file->filename,
                    'type' => $this->getFileTypeLabel($file->mime_type),
                    'accessed' => $file->retrieval_count,
                ];
            })
            ->toArray();
    }

    private function getFileTypeLabel(?string $type): string
    {
        if (! $type) {
            return 'Unknown';
        }

        $typeMap = [
            'application/pdf' => 'PDF Document',
            'application/msword' => 'Word Document',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Word Document',
            'text/plain' => 'Text File',
            'text/markdown' => 'Markdown File',
        ];

        return $typeMap[$type] ?? ucfirst(str_replace(['application/', 'text/'], '', $type));
    }
}
