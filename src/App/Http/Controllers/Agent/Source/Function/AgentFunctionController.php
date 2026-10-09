<?php

namespace App\Http\Controllers\Agent\Source\Function;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the agent's Functions page; its rows load from
 * {@see AgentFunctionsListController}.
 */
class AgentFunctionController
{
    public function __invoke(Agent $agent): Response
    {
        return Inertia::render('agents/Sources/Functions/index', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }
}
