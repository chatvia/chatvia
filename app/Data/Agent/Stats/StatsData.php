<?php

namespace App\Data\Agent\Stats;

use App\Models\Agent;
use App\Models\Llm;
use Spatie\LaravelData\Data;

class StatsData extends Data
{
    public function __construct(
        public int $totalMessages,
        public int $totalMembers,
        public ?float $avgResponseTime,
        public float $satisfactionRate,
        public array $messagesLastWeek,
        public array $modelUsage,
        public array $dailyMessages,
        public array $topKnowledge,
        public \DateTimeImmutable $lastUpdated,
    ) {}

    public static function fromAgent(Agent $agent)
    {
        return StatsData::from([
            'totalMessages' => $agent->countAssistantMessages(),
            'totalMembers' => $agent->members()->count(),
            'avgResponseTime' => $agent->avgResponseTime(),
            'satisfactionRate' => $agent->satisfactionRate(),
            'messagesLastWeek' => self::getMessagesLastWeek($agent),
            'modelUsage' => self::mostUsedModels($agent)->toArray(),
            'dailyMessages' => self::getDailyMessages($agent)->toArray(),
            'topKnowledge' => self::mostAccessedFiles($agent),
            'lastUpdated' => now(),
        ]);
    }

    private static function getMessagesLastWeek(Agent $agent): array
    {
        $startDate = now()->subDays(6)->startOfDay();
        $endDate = now()->endOfDay();

        // Get daily message counts for the last 7 days
        $messages =
            $agent
                ->messagesByData($startDate, $endDate)
                ->get();

        // Generate last 7 days with day names, ensuring all days are present
        $weeklyData = [];
        for ($i = 6; $i >= 0; $i--) {
            $date = now()->subDays($i);
            $dateKey = $date->format('Y-m-d');
            $dayName = $date->format('D'); // Mon, Tue, Wed, etc.

            $weeklyData[] = [
                'day' => $dayName,
                'date' => $dateKey,
                'value' => $messages[$dateKey] ?? 0,
            ];
        }

        return $weeklyData;
    }

    public static function mostUsedModels(Agent $agent)
    {
        $mostUsedModels = $agent
            ->mostUsedModels()
            ->get();

        $llms = Llm::query()
            ->whereIn('external_name', $mostUsedModels->pluck('model_external_name')->toArray())
            ->get();

        return $mostUsedModels->map(function ($item) use ($llms) {
            $model = $llms->firstWhere('external_name', $item->model_external_name);

            return [
                'name' => $model->name,
                'icon' => $model->icon,
                'count' => $item->usage_count,
                'percentage' => $item->percentage,
            ];
        });
    }

    public static function getDailyMessages($agent, int $days = 30)
    {
        return
            $agent
                ->dailyMessages($days)
                ->get()
                ->map(function ($item) {
                    return [
                        'date' => $item->date,
                        'messages' => $item->messages,
                    ];
                });
    }

    public static function mostAccessedFiles(Agent $agent, int $limit = 5): array
    {
        return $agent
            ->mostAccessedFiles()
            ->limit($limit)
            ->get()
            ->map(function ($file) use ($agent) {
                return [
                    'name' => $file->filename,
                    'type' => $agent->document_classification,
                    'accessed' => $file->retrieval_count,
                ];
            })
            ->toArray();
    }

    private static function getFileTypeLabel(?string $type): string
    {
        if (! $type) {
            return 'Unknown';
        }

        $typeMap = [
            'application/pdf' => 'PDF Document',
            'application/msword' => 'Word Document',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document' => 'Word Document',
            'text/plain' => 'TextCleaner File',
            'text/markdown' => 'Markdown File',
        ];

        return $typeMap[$type] ?? ucfirst(str_replace(['application/', 'text/'], '', $type));
    }
}
