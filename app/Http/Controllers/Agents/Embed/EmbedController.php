<?php

namespace App\Http\Controllers\Agents\Embed;

use App\Data\Resources\Agent\AgentResourceData;
use App\Models\Agent;
use App\Services\Auth\AgentMemberAuthTokenService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmbedController
{
    public function __invoke(Request $request, Agent $agent, AgentMemberAuthTokenService $authTokenService)
    {
        abort_if(
            boolean: ! $request->user()->can('view', $agent),
            code: 404,
        );

        if (! $agent->isStandalone()) {
            return back()->with('error', 'Agent is not a knowledge base!');
        }

        $authToken = null;

        if (! $agent->is_public) {
            $user = $request->user();
            $authToken = $authTokenService->generateTokenFromUser($agent, $user);
        }

        return Inertia::render('agents/Embed', [
            'agent' => AgentResourceData::fromModel($agent),
            'settings' => $agent->settings('appearance'),
            'default_settings' => config('model-settings.defaults.App\Models\Agent.appearance'),
            'embed_settings' => $agent->settings('embed'),
            'auth_token' => $authToken,
            'starters' => $agent->starters->toArray(),
        ]);
    }
}
