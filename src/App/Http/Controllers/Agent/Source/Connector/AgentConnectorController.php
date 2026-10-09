<?php

namespace App\Http\Controllers\Agent\Source\Connector;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the agent's Connectors page; its rows load from
 * {@see AgentConnectorsListController}.
 */
class AgentConnectorController
{
    public function __invoke(Agent $agent): Response
    {
        return Inertia::render('agents/Sources/Connectors/index', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }
}
