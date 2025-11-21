<?php

namespace App\Data\Agent\Stats;

use App\Models\Agent;
use App\Models\Llm;
use Spatie\LaravelData\Data;

class StatsData extends Data
{
    public function __construct(
        public int $totalMessages,
        public int $totalConversations,
        public int $totalMembers,
        public int $totalUserMessages,
        public int $uniqueSessions,
        public ?float $avgResponseTime,
        public ?float $avgFirstResponseTime,
        public ?float $avgMessagesPerConversation,
        public ?float $avgConversationDuration,
        public ?int $avgTokensPerMessage,
        public ?int $totalTokensUsed,
        public ?int $peakActivityHour,
        public int $conversationsWithFeedback,
        public array $feedback,
        public array $messagesLastWeek,
        public array $modelUsage,
        public array $countryUsage,
        public array $dailyMessages,
        public array $conversationsTrend,
        public array $activityByHour,
        public array $activityByDayOfWeek,
        public array $topKnowledge,
        public ?string $firstDataDate,
        public \DateTimeImmutable $lastUpdated,
    ) {}

    public static function fromAgent(Agent $agent, ?\Carbon\Carbon $startDate = null, ?\Carbon\Carbon $endDate = null)
    {
        $startDate = $startDate ?? now()->subDays(6)->startOfDay();
        $endDate = $endDate ?? now()->endOfDay();

        $days = $startDate->diffInDays($endDate) + 1;

        $firstDate = $agent->firstDataDate();

        return StatsData::from([
            'totalMessages' => $agent->countAssistantMessages(),
            'totalConversations' => $agent->countConversations(),
            'totalMembers' => $agent->members()->count(),
            'totalUserMessages' => $agent->totalUserMessages(),
            'uniqueSessions' => $agent->uniqueSessionsCount(),
            'avgResponseTime' => $agent->avgResponseTime(),
            'avgFirstResponseTime' => self::formatSeconds($agent->avgFirstResponseTime()),
            'avgMessagesPerConversation' => $agent->avgMessagesPerConversation(),
            'avgConversationDuration' => self::formatSeconds($agent->avgConversationDuration()),
            'avgTokensPerMessage' => $agent->avgTokensPerMessage(),
            'totalTokensUsed' => $agent->totalTokensUsed(),
            'peakActivityHour' => $agent->peakActivityHour(),
            'conversationsWithFeedback' => $agent->conversationsWithFeedback(),
            'feedback' => $agent->satisfactionRate(),
            'messagesLastWeek' => self::getMessagesInRange($agent, $startDate, $endDate),
            'modelUsage' => self::mostUsedModels($agent)->toArray(),
            'countryUsage' => self::getCountryStats($agent)->toArray(),
            'dailyMessages' => self::getDailyMessages($agent, $days)->toArray(),
            'conversationsTrend' => self::getConversationsTrend($agent, $startDate, $endDate)->toArray(),
            'activityByHour' => self::getActivityByHour($agent)->toArray(),
            'activityByDayOfWeek' => self::getActivityByDayOfWeek($agent)->toArray(),
            'topKnowledge' => self::mostAccessedFiles($agent),
            'firstDataDate' => $firstDate?->format('Y-m-d'),
            'lastUpdated' => now(),
        ]);
    }

    private static function formatSeconds(?float $seconds): ?float
    {
        if ($seconds === null) {
            return null;
        }

        return round($seconds, 1);
    }

    private static function getMessagesInRange(Agent $agent, \Carbon\Carbon $startDate, \Carbon\Carbon $endDate): array
    {
        // Get daily message counts for the date range
        $messages =
            $agent
                ->messagesByData($startDate, $endDate)
                ->get();

        // Generate all days in range with day names, ensuring all days are present
        $data = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dateKey = $currentDate->format('Y-m-d');
            $dayName = $currentDate->format('D'); // Mon, Tue, Wed, etc.

            $data[] = [
                'day' => $dayName,
                'date' => $dateKey,
                'value' => $messages[$dateKey] ?? 0,
            ];

            $currentDate->addDay();
        }

        return $data;
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
                'name' => $model->descriptive_name,
                'icon' => $model->icon,
                'count' => $item->usage_count,
                'percentage' => $item->percentage,
                'feedback' => [
                    'positive' => $item->positive_feedback,
                    'negative' => $item->negative_feedback,
                ],
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

    public static function getCountryStats(Agent $agent)
    {
        return $agent
            ->conversationsByCountry()
            ->get()
            ->map(function ($item) {
                return [
                    'country_code' => strtoupper($item->country_code),
                    'country_name' => $item->country_name,
                    'count' => $item->conversation_count,
                    'percentage' => $item->percentage,
                ];
            });
    }

    public static function getConversationsTrend(Agent $agent, ?\Carbon\Carbon $startDate = null, ?\Carbon\Carbon $endDate = null)
    {
        $startDate = $startDate ?? now()->subDays(6)->startOfDay();
        $endDate = $endDate ?? now()->endOfDay();

        $days = $startDate->diffInDays($endDate) + 1;

        return $agent
            ->conversationsTrend($days)
            ->get()
            ->map(function ($item) {
                return [
                    'date' => $item->date,
                    'conversations' => $item->conversations,
                ];
            });
    }

    public static function getActivityByHour(Agent $agent)
    {
        $hourlyData = $agent->conversationsByHour()->get()->keyBy('hour');

        // Fill in all 24 hours
        $result = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $result[] = [
                'hour' => $hour,
                'count' => $hourlyData->get($hour)?->conversation_count ?? 0,
            ];
        }

        return collect($result);
    }

    public static function getActivityByDayOfWeek(Agent $agent)
    {
        $daysData = $agent->conversationsByDayOfWeek()->get()->keyBy('day_of_week');

        $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        $result = [];
        for ($day = 0; $day < 7; $day++) {
            $result[] = [
                'day' => $dayNames[$day],
                'day_number' => $day,
                'count' => $daysData->get($day)?->conversation_count ?? 0,
            ];
        }

        return collect($result);
    }
}
