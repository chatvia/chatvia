<?php

namespace App\Http\Controllers\Agent\Analytics\Topic;

use App\Http\Requests\Agent\Analytics\AnalyticsDateRangeRequest;
use Domain\Agent\Models\Agent;
use Domain\Conversation\Topic\Data\TopicsResourceData;
use Domain\Conversation\Topic\Queries\TopicTrendsQuery;

/**
 * Topic counts per day and in total, for the agent's topic charts.
 */
class AgentTopicsDataController
{
    public function __invoke(AnalyticsDateRangeRequest $request, Agent $agent, TopicTrendsQuery $topicTrends): TopicsResourceData
    {
        $range = $request->toData();

        return $topicTrends->forAgent($agent, $range->start, $range->end);
    }
}
