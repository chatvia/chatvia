<?php

namespace App\Http\Controllers\Agent\Identity;

use App\Identity\Workspace\Services\AgentSignInTokenService;
use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Per-agent signing secret — the recoverable, encrypted-at-rest HMAC key the
 * owner uses to mint sign-in tokens for this agent (the chat `?token=` link and
 * the widget `data-auth` attribute). Shown in full on every visit because the
 * customer needs it on their backend to mint tokens; verified by
 * {@see AgentSignInTokenService}. Gated by `agent.permission:agent.identity.manage`
 * on the route.
 */
class AgentIdentityController
{
    public function __invoke(Agent $agent): Response
    {
        return Inertia::render('agents/security/Identity', [
            'agent' => AgentResourceData::fromModel($agent),
            'auth_secret' => $agent->ensureAuthSecret(),
            'embed_script_url' => url(config('chatvia.widget.script_url_embed')),
        ]);
    }
}
