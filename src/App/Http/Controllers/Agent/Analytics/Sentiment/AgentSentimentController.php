<?php

namespace App\Http\Controllers\Agent\Analytics\Sentiment;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The agent's sentiment analytics page; the chart data loads from
 * {@see AgentSentimentDataController}.
 */
class AgentSentimentController
{
    public function __invoke(Agent $agent): Response
    {
        return Inertia::render('dashboard/agents/pages/Sentiment', [
            'agent' => AgentResourceData::fromModel($agent),
            'enabled' => $agent->analyticsEnabled('sentiment'),
            'chatlogEnabled' => $agent->chatlogEnabled(),
        ]);
    }
}
