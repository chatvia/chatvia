<?php

namespace App\Support\Agents;

use App\Data\Agent\StatsData;
use App\Models\Agent;
use App\Models\AgentFile;
use App\Models\Message;
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
        return Message::query()
            ->where('agent_id', $this->agent->id)
            ->where('role', 'assistant')
            ->count();
    }

    public function getTotalMembers(): int
    {
        return $this->agent->members()->count();
    }

    public function averageResponseTime()
    {
        return Message::query()
            ->where('agent_id', $this->agent->id)
            ->where('role', 'assistant')
            ->selectRaw('ROUND(AVG(response_time_ms) / 1000, 1) as avg_response_time')
            ->value('avg_response_time');
    }

    public function satisfactionRate()
    {
        $result = Message::query()
            ->where('agent_id', $this->agent->id)
            ->where('role', 'assistant')
            ->whereNotNull('feedback')
            ->selectRaw('
                COUNT(CASE WHEN feedback = ? THEN 1 END) as positive_count,
                COUNT(CASE WHEN feedback = ? THEN 1 END) as negative_count,
                COUNT(*) as total_feedback,
                CASE
                    WHEN COUNT(*) = 0 THEN 0
                    ELSE ROUND(
                        (COUNT(CASE WHEN feedback = ? THEN 1 END) * 100.0) / COUNT(*),
                        1
                    )
                END as satisfaction_rate
            ', ['positive', 'negative', 'positive'])
            ->first();

        return $result->satisfaction_rate ?? 0;
    }

    public function getMessagesLastWeek(): int
    {
        return Message::query()
            ->where('agent_id', $this->agent->id)
            ->where('role', 'assistant')
            ->where('created_at', '>=', now()->subWeek())
            ->count();
    }

    public function getMostUsedModels()
    {
        return Message::query()
            ->where('agent_id', $this->agent->id)
            ->where('role', 'assistant')
            ->whereNotNull('model')
            ->selectRaw('
            model,
            COUNT(*) as usage_count,
            ROUND(
                (COUNT(*) * 100.0) / (
                    SELECT COUNT(*)
                    FROM messages
                    WHERE agent_id = ?
                    AND role = ?
                    AND model IS NOT NULL
                ),
                1
            ) as percentage
        ', [$this->agent->id, 'assistant'])
            ->groupBy('model')
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

        return Message::query()
            ->where('agent_id', $this->agent->id)
            ->where('role', 'assistant')
            ->where('created_at', '>=', $startDate)
            ->selectRaw('DATE(created_at) as date, COUNT(*) as messages')
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
