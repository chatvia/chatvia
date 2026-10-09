<?php

namespace Domain\Agent\Actions\Analytics;

use Domain\Agent\Data\Resources\Stats\ConversationTrendData;
use Domain\Agent\Data\Resources\Stats\CountryUsageData;
use Domain\Agent\Data\Resources\Stats\DailyMessagesData;
use Domain\Agent\Data\Resources\Stats\DayOfWeekActivityData;
use Domain\Agent\Data\Resources\Stats\FeedbackData;
use Domain\Agent\Data\Resources\Stats\HourlyActivityData;
use Domain\Agent\Data\Resources\Stats\MessageByDayData;
use Domain\Agent\Data\Resources\Stats\StatsResourceData;
use Domain\Agent\Data\Resources\Stats\TopKnowledgeData;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Data\Account\Audience\CountryShareData;
use Domain\Analytics\Data\Account\DailyCountData;
use Domain\Analytics\Data\Agent\Usage\FileUsageData;
use Domain\Analytics\Data\DateRangeData;
use Domain\Analytics\Queries\Agent\AgentActivityPatternQuery;
use Domain\Analytics\Queries\Agent\AgentCountryShareQuery;
use Domain\Analytics\Queries\Agent\AgentDailyCountsQuery;
use Domain\Analytics\Queries\Agent\AgentKnowledgeUsageQuery;
use Domain\Analytics\Queries\Agent\AgentTotalsQuery;
use Illuminate\Support\Carbon;

/**
 * Builds an agent's Analytics overview page for a date range: the all-time
 * headline numbers plus the per-day series for the range, the hour and
 * weekday patterns, the visitor countries and the most used knowledge files.
 * The numbers come from the Analytics read models; this only shapes them for
 * the page.
 */
class BuildAgentStatsAction
{
    private const array WEEKDAYS = ['Sunday', 'Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday'];

    public function __construct(
        private readonly AgentTotalsQuery $totals,
        private readonly AgentDailyCountsQuery $daily,
        private readonly AgentActivityPatternQuery $patterns,
        private readonly AgentCountryShareQuery $countries,
        private readonly AgentKnowledgeUsageQuery $knowledge,
    ) {}

    public function execute(Agent $agent, DateRangeData $range): StatsResourceData
    {
        $totals = $this->totals->get($agent);
        $replies = $this->daily->replies($agent, $range->start, $range->end);

        return new StatsResourceData(
            total_messages: $totals->assistantMessages,
            total_conversations: $totals->conversations,
            total_user_messages: $totals->userMessages,
            unique_sessions: $totals->uniqueSessions,
            avg_response_time: $totals->avgResponseMs !== null ? round($totals->avgResponseMs / 1000, 1) : null,
            avg_first_response_time: self::roundOrNull($totals->avgFirstResponseSeconds),
            avg_messages_per_conversation: self::roundOrNull($totals->avgMessagesPerConversation),
            avg_conversation_duration: self::roundOrNull($totals->avgConversationSeconds),
            avg_tokens_per_message: $totals->avgTokensPerReply !== null ? (int) round($totals->avgTokensPerReply) : null,
            total_tokens_used: $totals->totalTokens,
            peak_activity_hour: $totals->peakHour,
            conversations_with_feedback: $totals->conversationsWithFeedback,
            feedback: new FeedbackData(
                positive: $totals->positiveFeedback,
                negative: $totals->negativeFeedback,
                total: $totals->positiveFeedback + $totals->negativeFeedback,
            ),
            messages_last_week: $this->everyDay($replies, $range->start, $range->end),
            country_usage: array_map(self::countryUsage(...), $this->countries->get($agent)),
            daily_messages: array_map(
                static fn (DailyCountData $day): DailyMessagesData => new DailyMessagesData($day->date, $day->count),
                $replies,
            ),
            conversations_trend: array_map(
                static fn (DailyCountData $day): ConversationTrendData => new ConversationTrendData($day->date, $day->count),
                $this->daily->conversations($agent, $range->start, $range->end),
            ),
            activity_by_hour: $this->byHour($agent),
            activity_by_day_of_week: $this->byWeekday($agent),
            top_knowledge: array_map(self::topKnowledge(...), $this->knowledge->topFiles($agent)),
            first_data_date: $totals->firstDataDate,
            last_updated: now()->toDateTimeImmutable(),
        );
    }

    /**
     * One entry per day of the range, zero where nothing was sent.
     *
     * @param  list<DailyCountData>  $days
     * @return list<MessageByDayData>
     */
    private function everyDay(array $days, Carbon $start, Carbon $end): array
    {
        $counts = [];

        foreach ($days as $day) {
            $counts[$day->date] = $day->count;
        }

        $series = [];

        for ($date = $start->copy(); $date->lte($end); $date->addDay()) {
            $key = $date->format('Y-m-d');
            $series[] = new MessageByDayData(day: $date->format('D'), date: $key, value: $counts[$key] ?? 0);
        }

        return $series;
    }

    /**
     * All 24 hours, zero where no conversation started.
     *
     * @return list<HourlyActivityData>
     */
    private function byHour(Agent $agent): array
    {
        $counts = [];

        foreach ($this->patterns->byHour($agent) as $hour) {
            $counts[$hour->hour] = $hour->count;
        }

        return array_map(static fn (int $hour): HourlyActivityData => new HourlyActivityData($hour, $counts[$hour] ?? 0), range(0, 23));
    }

    /**
     * All 7 weekdays from Sunday, zero where no conversation started.
     *
     * @return list<DayOfWeekActivityData>
     */
    private function byWeekday(Agent $agent): array
    {
        $counts = [];

        foreach ($this->patterns->byWeekday($agent) as $weekday) {
            $counts[$weekday->weekday] = $weekday->count;
        }

        return array_map(
            static fn (int $day): DayOfWeekActivityData => new DayOfWeekActivityData(self::WEEKDAYS[$day], $day, $counts[$day] ?? 0),
            range(0, 6),
        );
    }

    private static function countryUsage(CountryShareData $country): CountryUsageData
    {
        return new CountryUsageData($country->code, $country->name, $country->conversations, $country->pct);
    }

    private static function topKnowledge(FileUsageData $file): TopKnowledgeData
    {
        return new TopKnowledgeData($file->filename, $file->classification ?? 'unknown', $file->retrievals);
    }

    private static function roundOrNull(?float $value): ?float
    {
        return $value !== null ? round($value, 1) : null;
    }
}
