<?php

namespace Domain\Agent\Data\Resources\Stats;

use Domain\Agent\Actions\Analytics\BuildAgentStatsAction;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The agent Analytics overview page: all-time headline numbers, the per-day
 * series for the selected range, activity patterns, visitor countries and the
 * most used knowledge files. Built by {@see BuildAgentStatsAction}.
 */
#[TypeScript]
class StatsResourceData extends Data
{
    /**
     * @param  MessageByDayData[]  $messages_last_week
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
}
