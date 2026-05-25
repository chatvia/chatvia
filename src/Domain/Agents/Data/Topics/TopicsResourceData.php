<?php

namespace Domain\Agents\Data\Topics;

use Domain\Agents\Models\Agent;
use Illuminate\Support\Carbon;
use Spatie\LaravelData\Attributes\DataCollectionOf;
use Spatie\LaravelData\Data;
use Spatie\LaravelData\DataCollection;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class TopicsResourceData extends Data
{
    public function __construct(
        #[DataCollectionOf(TopicTrendData::class)]
        public DataCollection $topics_by_date,
        #[DataCollectionOf(TopicSummaryData::class)]
        public DataCollection $total_topics,
        public int $total_count,
    ) {}

    public static function fromAgent(Agent $agent, ?Carbon $startDate = null, ?Carbon $endDate = null): self
    {
        $startDate = $startDate ?? now()->subDays(6)->startOfDay();
        $endDate = $endDate ?? now()->endOfDay();

        $topicsByDate = $agent->topicsByDateRange($startDate, $endDate);
        $totalTopics = $agent->topTopics($startDate, $endDate);

        // Generate all dates in range
        $dates = [];
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $dates[] = $currentDate->format('Y-m-d');
            $currentDate->addDay();
        }

        // Organize data by topic using Data objects
        $topicsData = [];
        foreach ($totalTopics as $topic) {
            $trendData = [];
            foreach ($dates as $date) {
                $record = $topicsByDate->first(
                    fn ($item): bool => $item->topic_id === $topic->topic_id && $item->date === $date
                );

                $trendData[] = TopicCountByDateData::from([
                    'date' => $date,
                    'count' => $record->count ?? 0,
                ]);
            }

            $topicsData[] = TopicTrendData::from([
                'topic_name' => $topic->topic_name,
                'counts' => new DataCollection(TopicCountByDateData::class, $trendData),
            ]);
        }

        $totalTopicsData = $totalTopics->map(fn ($topic): TopicSummaryData => TopicSummaryData::from([
            'id' => $topic->topic_id,
            'name' => $topic->topic_name,
            'count' => $topic->total_count,
        ]))->toArray();

        return new self(
            topics_by_date: new DataCollection(TopicTrendData::class, $topicsData),
            total_topics: new DataCollection(TopicSummaryData::class, $totalTopicsData),
            total_count: $totalTopics->count(),
        );
    }
}
