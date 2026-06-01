<?php

namespace App\Http\Controllers\Agents\Sources\Connectors;

use Domain\Agents\Data\Resources\Account\AgentResourceData;
use Domain\Agents\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentConnectorsController
{
    public function __invoke(Request $request, Agent $agent): Response
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        return Inertia::render('agents/Sources/Connectors/index', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }
}
