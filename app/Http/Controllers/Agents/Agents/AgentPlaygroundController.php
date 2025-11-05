<?php

namespace App\Http\Controllers\Agents\Agents;

use App\Models\Agent;
use App\Services\Auth\AgentMemberAuthTokenService;
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
            $authToken = $authTokenService->generateTokenFromUser($agent, $user);
        }

        return Inertia::render('agents/Playground', [
            'agent' => $agent,
            'authToken' => $authToken,
        ]);
    }
}
