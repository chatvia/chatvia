<?php

namespace App\Http\Controllers\Agents\Sources\Functions;

use Domain\Agents\Data\Resources\AgentResourceData;
use Domain\Agents\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentFunctionsController
{
    public function __invoke(Request $request, Agent $agent): Response
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        return Inertia::render('agents/Sources/Functions/index', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }
}
