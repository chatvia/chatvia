<?php

namespace Domain\Agents\Data\Resources\Stats;

use App\Country\Models\Country;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\Llm;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class StatsResourceData extends Data
{
    /**
     * @param  MessageByDayData[]  $messages_last_week
     * @param  ModelUsageData[]  $model_usage
     * @param  CountryUsageData[]  $country_usage
     * @param  DailyMessagesData[]  $daily_messages
     * @param  ConversationTrendData[]  $conversations_trend
     * @param  HourlyActivityData[]  $activity_by_hour
     * @param  DayOfWeekActivityData[]  $activity_by_day_of_week
     * @param  TopKnowledgeData[]  $top_knowledge
     */
    public function __construct(
        public int $total_messages,
        public int $total_conversations,
        public int $total_members,
        public int $total_user_messages,
        public int $unique_sessions,
        public ?float $avg_response_time,
        public ?float $avg_first_response_time,
        public ?float $avg_messages_per_conversation,
        public ?float $avg_conversation_duration,
        public ?int $avg_tokens_per_message,
        public ?int $total_tokens_used,
        public ?int $peak_activity_hour,
        public int $conversations_with_feedback,
        public FeedbackData $feedback,
        /** @var MessageByDayData[] */
        public array $messages_last_week,
        /** @var ModelUsageData[] */
        public array $model_usage,
        /** @var CountryUsageData[] */
        public array $country_usage,
        /** @var DailyMessagesData[] */
        public array $daily_messages,
        /** @var ConversationTrendData[] */
        public array $conversations_trend,
        /** @var HourlyActivityData[] */
        public array $activity_by_hour,
        /** @var DayOfWeekActivityData[] */
        public array $activity_by_day_of_week,
        /** @var TopKnowledgeData[] */
        public array $top_knowledge,
        public ?string $first_data_date,
        public \DateTimeImmutable $last_updated,
    ) {}

    public static function fromAgent(Agent $agent, ?Carbon $startDate = null, ?Carbon $endDate = null): self
    {
        $startDate = $startDate ?? now()->subDays(6)->startOfDay();
        $endDate = $endDate ?? now()->endOfDay();

        $days = (int) $startDate->diffInDays($endDate) + 1;

        // Single-pass aggregate replaces ~12 previously-separate queries.
        $agg = $agent->aggregateStats();

        $positive = (int) ($agg->positive_feedback ?? 0);
        $negative = (int) ($agg->negative_feedback ?? 0);

        return self::from([
            'total_messages' => (int) ($agg->total_assistant ?? 0),
            'total_conversations' => (int) ($agg->total_conversations ?? 0),
            'total_members' => 0,
            'total_user_messages' => (int) ($agg->total_user ?? 0),
            'unique_sessions' => (int) ($agg->unique_sessions ?? 0),
            'avg_response_time' => $agg->avg_response_ms !== null
                ? round(((float) $agg->avg_response_ms) / 1000, 1)
                : null,
            'avg_first_response_time' => self::formatSeconds(
                $agg->avg_first_response_seconds !== null ? (float) $agg->avg_first_response_seconds : null
            ),
            'avg_messages_per_conversation' => $agg->avg_messages_per_conversation !== null
                ? round((float) $agg->avg_messages_per_conversation, 1)
                : null,
            'avg_conversation_duration' => self::formatSeconds(
                $agg->avg_conversation_duration_seconds !== null ? (float) $agg->avg_conversation_duration_seconds : null
            ),
            'avg_tokens_per_message' => $agg->avg_tokens !== null ? (int) round((float) $agg->avg_tokens) : null,
            'total_tokens_used' => $agg->total_tokens !== null ? (int) $agg->total_tokens : null,
            'peak_activity_hour' => $agg->peak_hour !== null ? (int) $agg->peak_hour : null,
            'conversations_with_feedback' => (int) ($agg->conversations_with_feedback ?? 0),
            'feedback' => [
                'positive' => $positive,
                'negative' => $negative,
                'total' => $positive + $negative,
            ],
            'messages_last_week' => self::getMessagesInRange($agent, $startDate, $endDate),
            'model_usage' => self::mostUsedModels($agent),
            'country_usage' => self::getCountryStats($agent),
            'daily_messages' => self::getDailyMessages($agent, $days),
            'conversations_trend' => self::getConversationsTrend($agent, $startDate, $endDate),
            'activity_by_hour' => self::getActivityByHour($agent),
            'activity_by_day_of_week' => self::getActivityByDayOfWeek($agent),
            'top_knowledge' => self::mostAccessedFiles($agent),
            'first_data_date' => $agg->first_data_date ? (string) $agg->first_data_date : null,
            'last_updated' => now(),
        ]);
    }

    private static function formatSeconds(?float $seconds): ?float
    {
        if ($seconds === null) {
            return null;
        }

        return round($seconds, 1);
    }

    private static function getMessagesInRange(Agent $agent, Carbon $startDate, Carbon $endDate): array
    {
        // messagesByData returns rows of {date, count}; key by date string so
        // the day-by-day lookup below resolves instead of silently returning 0.
        $messages = $agent->messagesByData($startDate, $endDate)
            ->get()
            ->pluck('count', 'date');

        $data = [];
        $currentDate = $startDate->copy();

        while ($currentDate->lte($endDate)) {
            $dateKey = $currentDate->format('Y-m-d');
            $dayName = $currentDate->format('D');

            $data[] = [
                'day' => $dayName,
                'date' => $dateKey,
                'value' => (int) ($messages[$dateKey] ?? 0),
            ];

            $currentDate->addDay();
        }

        return $data;
    }

    public static function mostUsedModels(Agent $agent): array
    {
        $mostUsedModels = $agent->mostUsedModels()->get();

        $llms = Llm::query()
            ->whereIn('external_name', $mostUsedModels->pluck('model_external_name')->toArray())
            ->get();

        // Null-safe: a model may have been renamed or removed from `llms` since
        // its rollups were written. Fall back to the raw external name rather
        // than crashing the analytics page on a stale dimension.
        return $mostUsedModels->map(function ($item) use ($llms) {
            $model = $llms->firstWhere('external_name', $item->model_external_name);

            return [
                'name' => $model?->descriptive_name ?? $item->model_external_name,
                'icon' => $model?->icon,
                'count' => $item->usage_count,
                'percentage' => $item->percentage,
                'feedback' => [
                    'positive' => $item->positive_feedback,
                    'negative' => $item->negative_feedback,
                ],
            ];
        })->toArray();
    }

    public static function getDailyMessages(Agent $agent, int $days = 30): array
    {
        return $agent
            ->dailyMessages($days)
            ->get()
            ->map(fn ($item): array => [
                'date' => $item->date,
                'messages' => $item->messages,
            ])
            ->toArray();
    }

    public static function mostAccessedFiles(Agent $agent, int $limit = 5): array
    {
        return $agent
            ->mostAccessedFiles()
            ->limit($limit)
            ->get()
            ->map(fn ($file): array => [
                'name' => $file->filename,
                'type' => $file->document_classification ?? 'unknown',
                'accessed' => $file->retrieval_count,
            ])
            ->toArray();
    }

    public static function getCountryStats(Agent $agent): array
    {
        $rows = $agent->conversationsByCountry()->get();

        if ($rows->isEmpty()) {
            return [];
        }

        // Look up country names on the main DB; the analytics rollup only
        // carries the ISO code, since countries lives on a different connection.
        // countries.code is stored lowercase, so look up in the original case
        // and uppercase only at the boundary for the frontend.
        $codes = $rows->pluck('country_code')->map(fn ($code): string => (string) $code)->all();
        $names = Country::query()
            ->whereIn('code', $codes)
            ->pluck('name', 'code');

        return $rows->map(fn ($item): array => [
            'country_code' => strtoupper((string) $item->country_code),
            'country_name' => $names[(string) $item->country_code] ?? strtoupper((string) $item->country_code),
            'count' => (int) $item->conversation_count,
            'percentage' => (float) $item->percentage,
        ])->toArray();
    }

    public static function getConversationsTrend(Agent $agent, ?Carbon $startDate = null, ?Carbon $endDate = null): array
    {
        $startDate = $startDate ?? now()->subDays(6)->startOfDay();
        $endDate = $endDate ?? now()->endOfDay();

        $days = (int) $startDate->diffInDays($endDate) + 1;

        return $agent
            ->conversationsTrend($days)
            ->get()
            ->map(fn ($item): array => [
                'date' => $item->date,
                'conversations' => $item->conversations,
            ])
            ->toArray();
    }

    public static function getActivityByHour(Agent $agent): array
    {
        $hourlyData = $agent->conversationsByHour()->get()->keyBy('hour');

        $result = [];
        for ($hour = 0; $hour < 24; $hour++) {
            $result[] = [
                'hour' => $hour,
                'count' => $hourlyData->get($hour)->conversation_count ?? 0,
            ];
        }

        return $result;
    }

    public static function getActivityByDayOfWeek(Agent $agent): array
    {
        $daysData = $agent->conversationsByDayOfWeek()->get()->keyBy('day_of_week');

        $dayNames = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

        $result = [];
        for ($day = 0; $day < 7; $day++) {
            $result[] = [
                'day' => $dayNames[$day],
                'day_number' => $day,
                'count' => $daysData->get($day)->conversation_count ?? 0,
            ];
        }

        return $result;
    }
}
