<?php

namespace App\Http\Controllers\Agent\Playground;

use App\Chatter\Services\ChatterAuthTokenService;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
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
