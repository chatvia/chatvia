<?php

namespace App\Http\Controllers\Agents\Embed;

use App\Auth\Services\ChatterAuthTokenService;
use Domain\Agents\Data\Resources\AgentResourceData;
use Domain\Agents\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EmbedController
{
    public function __invoke(Request $request, Agent $agent, ChatterAuthTokenService $authTokenService)
    {
        abort_if(
            boolean: ! $request->user()->can('view', $agent),
            code: 404,
        );

        $authToken = null;

        if (! $agent->is_public) {
            $user = $request->user();
            $authToken = $authTokenService->generateToken($agent, $user);
        }

        $agent->loadMissing('agentTemplate');

        return Inertia::render('agents/Embed', [
            'agent' => AgentResourceData::fromModel($agent),
            'settings' => $agent->settings('appearance'),
            'default_settings' => config('model-settings.defaults.Domain\Agents\Models\Agent.appearance'),
            'embed_settings' => $agent->settings('embed'),
            'auth_token' => $authToken,
            'starters' => $agent->resolvedStarters(),
            'agent_template_id' => $agent->agentTemplate?->external_id,
            'attached_template' => $agent->agentTemplate
                ? ['id' => $agent->agentTemplate->external_id, 'name' => $agent->agentTemplate->name]
                : null,
        ]);
    }
}
