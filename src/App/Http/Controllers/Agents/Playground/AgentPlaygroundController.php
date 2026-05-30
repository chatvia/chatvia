<?php

namespace App\Http\Controllers\Agents\Playground;

use App\Auth\Services\ChatterAuthTokenService;
use Domain\Agents\Data\Resources\AgentResourceData;
use Domain\Agents\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentPlaygroundController
{
    public function __invoke(Request $request, Agent $agent, ChatterAuthTokenService $authTokenService)
    {
        $authToken = null;

        if (! $agent->is_public) {
            $user = $request->user();
            $authToken = $authTokenService->generateToken($agent, $user);
        }

        return Inertia::render('agents/Playground', [
            'agent' => AgentResourceData::fromModel($agent),
            'authToken' => $authToken,
        ]);
    }
}
