<?php

namespace App\Http\Controllers\Agent\Analytics\Overview;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The agent's analytics overview page. Its numbers load from
 * {@see AgentAnalyticsDataController} and {@see AgentChartDataController}.
 */
class AgentAnalyticsController
{
    public function __invoke(Agent $agent): Response
    {
        return Inertia::render('agents/Analytics', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }
}
