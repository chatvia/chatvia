<?php

namespace Domain\Analytics\Queries\Agent;

use Domain\Agent\Models\Agent;
use Domain\Analytics\Data\Agent\Topics\TopicDayCountData;
use Domain\Analytics\Data\Agent\Topics\TopicTotalData;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * What an agent's conversations were about in an inclusive date range, from
 * the per-day topic rollup. The rollup lives on the analytics connection and
 * `topics` on the default one, so counts are grouped by topic id first and the
 * names looked up afterwards (no cross-database join).
 */
class AgentTopicTrendQuery extends AgentAnalyticsQuery
{
    /**
     * Conversations per topic per day, by date then topic name.
     *
     * @return list<TopicDayCountData>
     */
    public function perDay(Agent $agent, Carbon $start, Carbon $end): array
    {
        $rows = $this->inRange($agent, $start, $end)
            ->groupBy('bucket_day', 'topic_id')
            ->select([
                DB::raw('bucket_day::text as date'),
                'topic_id',
                DB::raw('SUM(conversation_count) as count'),
            ])
            ->orderBy('date')
            ->get();

        $names = $this->topicNames($rows->pluck('topic_id'));

        return $rows
            ->map(static fn (object $row): TopicDayCountData => new TopicDayCountData(
                date: (string) $row->date,
                topicId: (int) $row->topic_id,
                topicName: $names[$row->topic_id] ?? null,
                count: (int) $row->count,
            ))
            ->sortBy([['date', 'asc'], ['topicName', 'asc']])
            ->values()
            ->all();
    }

    /**
     * The most discussed topics over the range, busiest first.
     *
     * @return list<TopicTotalData>
     */
    public function top(Agent $agent, Carbon $start, Carbon $end, int $limit = 10): array
    {
        $rows = $this->inRange($agent, $start, $end)
            ->groupBy('topic_id')
            ->select(['topic_id', DB::raw('SUM(conversation_count) as total_count')])
            ->orderByDesc('total_count')
            ->limit($limit)
            ->get();

        $names = $this->topicNames($rows->pluck('topic_id'));

        return $rows
            ->map(static fn (object $row): TopicTotalData => new TopicTotalData(
                topicId: (int) $row->topic_id,
                topicName: $names[$row->topic_id] ?? null,
                count: (int) $row->total_count,
            ))
            ->all();
    }

    private function inRange(Agent $agent, Carbon $start, Carbon $end): Builder
    {
        return $this->dailyRollup($agent, 'analytics_agent_topic_daily')
            ->whereRaw('bucket_day >= ?', [$start->format('Y-m-d')])
            ->whereRaw('bucket_day <= ?', [$end->format('Y-m-d')]);
    }

    /**
     * @param  Collection<int, int|string>  $topicIds
     * @return Collection<int|string, string>
     */
    private function topicNames(Collection $topicIds): Collection
    {
        if ($topicIds->isEmpty()) {
            return collect();
        }

        return DB::table('topics')->whereIn('id', $topicIds->unique()->all())->pluck('topic', 'id');
    }
}
