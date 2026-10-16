<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Analytics;

use App\Http\Requests\Api\V1\Analytics\AnalyticsRangeRequest;
use App\Http\Resources\Api\V1\Analytics\DimensionBreakdownData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Models\Agent;
use Domain\Analytics\Actions\Dimensions\BuildMessageDimensionBreakdownAction;
use Domain\Analytics\Enums\MessageDimension;

/**
 * Daily visitor tone over the public API — the data behind the dashboard's
 * tone chart, gated by `agent.analytics.tone`.
 */
#[Group(name: 'Analytics', weight: 140)]
class ToneAnalyticsController
{
    /**
     * Get tone breakdown
     *
     * Visitor-message tone per day and summed over the range. Row keys are
     * the tone values plus `unspecified` for messages that were never
     * analysed.
     *
     * @return array{data: DimensionBreakdownData}
     */
    public function __invoke(AnalyticsRangeRequest $request, Agent $agent, BuildMessageDimensionBreakdownAction $breakdown): array
    {
        return ['data' => DimensionBreakdownData::fromBreakdown($breakdown->execute($agent, MessageDimension::Tone, $request->toData()))];
    }
}
