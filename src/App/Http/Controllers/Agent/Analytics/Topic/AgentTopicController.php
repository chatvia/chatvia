<?php

namespace App\Http\Controllers\Agent\Analytics\Topic;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The agent's topic analytics page; its data loads from
 * {@see AgentTopicsDataController} and {@see SuggestedTopicsDataController}.
 */
class AgentTopicController
{
    public function __invoke(Agent $agent): Response
    {
        return Inertia::render('dashboard/agents/pages/Topics', [
            'agent' => AgentResourceData::fromModel($agent),
            'enabled' => $agent->analyticsEnabled('topics'),
            'chatlogEnabled' => $agent->chatlogEnabled(),
        ]);
    }
}
