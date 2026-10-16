<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Requests\Api\V1\Analytics\AnalyticsRangeRequest;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Actions\Analytics\BuildAgentStatsAction;
use Domain\Agent\Data\Resources\Stats\StatsResourceData;
use Domain\Agent\Models\Agent;

/**
 * The agent analytics overview over the public API — the same numbers as the
 * dashboard's Analytics page, gated by `agent.analytics.chats`.
 */
#[Group(name: 'Analytics', weight: 140)]
class OverviewAnalyticsController
{
    /**
     * Get analytics overview
     *
     * Headline numbers (messages, conversations, response times, feedback),
     * the per-day series for the selected range, activity patterns, visitor
     * countries and the most used knowledge files.
     *
     * @return array{data: StatsResourceData}
     */
    public function __invoke(AnalyticsRangeRequest $request, Agent $agent, BuildAgentStatsAction $build): array
    {
        return ['data' => $build->execute($agent, $request->toData())];
    }
}
