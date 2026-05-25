<?php

namespace App\Http\Controllers\Agents;

use App\Auth\Services\AgentMemberAuthTokenService;
use Domain\Agents\Data\Resources\AgentResourceData;
use Domain\Agents\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentPlaygroundController
{
    public function __invoke(Request $request, Agent $agent, AgentMemberAuthTokenService $authTokenService)
    {
        if ($agent->isWidget()) {
            return to_route('agents.widget', ['agent' => $agent]);
        }

        $authToken = null;

        if (! $agent->is_public && $agent->isStandalone()) {
            $user = $request->user();
            $authToken = $authTokenService->generateToken($agent, $user);
        }

        return Inertia::render('agents/Playground', [
            'agent' => AgentResourceData::fromModel($agent),
            'authToken' => $authToken,
        ]);
    }
}
