<?php

namespace App\Http\Controllers\Agent\Analytics\Tone;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The agent's tone analytics page; the chart data loads from
 * {@see AgentToneDataController}.
 */
class AgentToneController
{
    public function __invoke(Agent $agent): Response
    {
        return Inertia::render('agents/Tone', [
            'agent' => AgentResourceData::fromModel($agent),
            'enabled' => $agent->analyticsEnabled('tone'),
            'chatlogEnabled' => $agent->chatlogEnabled(),
        ]);
    }
}
