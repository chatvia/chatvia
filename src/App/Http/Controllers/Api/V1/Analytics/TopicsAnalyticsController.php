<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Requests\Api\V1\Analytics\AnalyticsRangeRequest;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Topic\Data\TopicsResourceData;
use Domain\Conversation\Topic\Queries\TopicTrendsQuery;

/**
 * Topic trends over the public API — the data behind the dashboard's topic
 * charts, gated by `agent.analytics.topics`.
 */
#[Group(name: 'Analytics', weight: 140)]
class TopicsAnalyticsController
{
    /**
     * Get topic trends
     *
     * Each busy topic's conversation counts per day over the range, the
     * totals per topic and the overall total.
     *
     * @return array{data: TopicsResourceData}
     */
    public function __invoke(AnalyticsRangeRequest $request, Agent $agent, TopicTrendsQuery $topicTrends): array
    {
        $range = $request->toData();

        return ['data' => $topicTrends->forAgent($agent, $range->start, $range->end)];
    }
}
