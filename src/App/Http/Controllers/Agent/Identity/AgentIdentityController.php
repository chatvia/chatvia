<?php

namespace App\Http\Controllers\Agent\Identity;

use App\Http\Controllers\Controller;
use App\Identity\Workspace\Services\AgentSignInTokenService;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Per-agent signing secret — the recoverable, encrypted-at-rest HMAC key the
 * owner uses to mint sign-in tokens for this agent (the chat `?token=` link and
 * the widget `data-auth` attribute). Shown in full on every visit because the
 * customer needs it on their backend to mint tokens; verified by
 * {@see AgentSignInTokenService}.
 */
class AgentIdentityController extends Controller
{
    public function __invoke(Request $request, Agent $agent): Response
    {
        abort_unless(has_permission_to_agent($agent, 'agent.identity.manage'), 404);

        return Inertia::render('agents/IdentityVerification', [
            'agent' => $agent,
            'auth_secret' => $agent->ensureAuthSecret(),
            'embed_script_url' => config('widget.script_url_embed'),
        ]);
    }
}
