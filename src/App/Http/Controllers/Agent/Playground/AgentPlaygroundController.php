<?php

namespace App\Http\Controllers\Agent\Playground;

use App\Identity\Chatter\Services\ChatterAuthTokens;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The dashboard playground: try the agent on every surface. A protected
 * agent gets a short-lived sign-in token so the preview can chat as the
 * signed-in user.
 */
class AgentPlaygroundController
{
    public function __invoke(Request $request, Agent $agent, ChatterAuthTokens $authTokens): Response
    {
        $authToken = null;

        if (! $agent->is_public) {
            $user = $request->user();
            $authToken = $authTokens->generateToken($agent, $user);
        }

        return Inertia::render('agents/Playground', [
            'agent' => AgentResourceData::fromModel($agent),
            'authToken' => $authToken,
        ]);
    }
}
