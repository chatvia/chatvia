<?php

namespace App\Http\Controllers\Agent\Source\File\Pages;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the agent's knowledge overview page. Agent access is checked by
 * the route middleware.
 */
class AgentKnowledgeSourceController
{
    public function __invoke(Agent $agent): Response
    {
        return Inertia::render('agents/Sources/Sources', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }
}
