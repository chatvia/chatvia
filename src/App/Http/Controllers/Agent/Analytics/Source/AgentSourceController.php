<?php

namespace App\Http\Controllers\Agent\Analytics\Source;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The agent's source-usage analytics page; its data loads from
 * {@see AgentSourcesDataController}.
 */
class AgentSourceController
{
    public function __invoke(Agent $agent): Response
    {
        return Inertia::render('dashboard/agents/pages/Sources', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }
}
