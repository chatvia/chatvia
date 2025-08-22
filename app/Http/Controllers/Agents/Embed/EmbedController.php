<?php

namespace App\Http\Controllers\Agents\Embed;

use App\Models\Agent;
use App\Services\Auth\AgentMemberAuthTokenService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmbedController
{
    public function __invoke(Request $request, Agent $agent, AgentMemberAuthTokenService $authTokenService)
    {
        $authToken = null;

        if (! $agent->is_public) {
            $user = $request->user();
            $authToken = $authTokenService->generateTokenFromUser($agent, $user);
        }

        return Inertia::render('agents/Embed', [
            'agent' => $agent,
            'embed_settings' => $agent->settings('embed'),
            'auth_token' => $authToken,
        ]);
    }
}
