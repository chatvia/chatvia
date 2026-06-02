<?php

namespace App\Http\Controllers\Agent\Source\Function;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AgentFunctionController
{
    public function __invoke(Request $request, Agent $agent): Response
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        return Inertia::render('agents/Sources/Functions/index', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }
}
