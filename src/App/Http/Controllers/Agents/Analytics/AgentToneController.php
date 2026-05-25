<?php

namespace App\Http\Controllers\Agents\Analytics;

use App\Auth\Models\User;
use Domain\Agents\Data\Resources\AgentResourceData;
use Domain\Agents\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentToneController
{
    public function __invoke(Request $request, Agent $agent)
    {
        abort_if(
            boolean: $request->user()->cannot('view', $agent),
            code: 404,
        );

        /** @var User $user */
        $user = $agent->user;

        return Inertia::render('agents/Tone', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }
}
