<?php

namespace Domain\Conversation\Topic\Queries;

use Domain\Agent\Models\Agent;
use Domain\Analytics\Data\Agent\Topics\TopicDayCountData;
use Domain\Analytics\Data\Agent\Topics\TopicTotalData;
use Domain\Analytics\Data\DateRangeData;
use Domain\Analytics\Queries\Agent\AgentTopicTrendQuery;
use Domain\Conversation\Topic\Data\TopicCountByDateData;
use Domain\Conversation\Topic\Data\TopicsResourceData;
use Domain\Conversation\Topic\Data\TopicSummaryData;
use Domain\Conversation\Topic\Data\TopicTrendData;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Spatie\LaravelData\DataCollection;

/**
 * The agent's topic charts: for each of its busiest topics, how many
 * conversations touched it on every day of the range (days without any count
 * as zero), plus the totals.
 */
class TopicTrendsQuery
{
    public function __construct(
        private readonly AgentTopicTrendQuery $trend,
    ) {}

    public function forAgent(Agent $agent, ?Carbon $start = null, ?Carbon $end = null): TopicsResourceData
    {
        $start ??= DateRangeData::defaultStart();
        $end ??= now()->endOfDay();

        $topTopics = $this->trend->top($agent, $start, $end);
        $countsByTopicAndDay = collect($this->trend->perDay($agent, $start, $end))
            ->keyBy(static fn (TopicDayCountData $row): string => "{$row->topicId}|{$row->date}");

        return new TopicsResourceData(
            topics_by_date: new DataCollection(TopicTrendData::class, array_map(
                fn (TopicTotalData $topic): TopicTrendData => $this->dailyTrend($topic, $this->days($start, $end), $countsByTopicAndDay),
                $topTopics,
            )),
            total_topics: new DataCollection(TopicSummaryData::class, array_map(
                static fn (TopicTotalData $topic): TopicSummaryData => new TopicSummaryData(
                    id: $topic->topicId,
                    name: $topic->topicName ?? '',
                    count: $topic->count,
                ),
                $topTopics,
            )),
            total_count: count($topTopics),
        );
    }

    /**
     * @param  list<string>  $days
     * @param  Collection<string, TopicDayCountData>  $countsByTopicAndDay
     */
    private function dailyTrend(TopicTotalData $topic, array $days, Collection $countsByTopicAndDay): TopicTrendData
    {
        return new TopicTrendData(
            topic_name: $topic->topicName ?? '',
            counts: new DataCollection(TopicCountByDateData::class, array_map(
                static fn (string $day): TopicCountByDateData => new TopicCountByDateData(
                    date: $day,
                    count: $countsByTopicAndDay->get("{$topic->topicId}|{$day}")?->count ?? 0,
                ),
                $days,
            )),
        );
    }

    /**
     * Every day of the range, as `Y-m-d`.
     *
     * @return list<string>
     */
    private function days(Carbon $start, Carbon $end): array
    {
        $days = [];

        for ($day = $start->copy(); $day->lte($end); $day->addDay()) {
            $days[] = $day->format('Y-m-d');
        }

        return $days;
    }
}
