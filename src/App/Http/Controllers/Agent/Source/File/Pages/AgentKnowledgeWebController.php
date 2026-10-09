<?php

namespace App\Http\Controllers\Agent\Source\File\Pages;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the agent's Web knowledge page. Agent access and the
 * `agent.sources.train.web` permission are checked by the route middleware.
 */
class AgentKnowledgeWebController
{
    public function __invoke(Agent $agent): Response
    {
        return Inertia::render('agents/Sources/Web', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }
}
