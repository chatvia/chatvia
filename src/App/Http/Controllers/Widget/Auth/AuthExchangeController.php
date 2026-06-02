<?php

namespace App\Http\Controllers\Widget\Auth;

use App\Workspace\Services\AgentSignInTokenService;
use App\Workspace\Services\WidgetAuthSessionService;
use App\Widget\WidgetVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Exchanges a signed bootstrap token (the embed's `data-auth` value) for an
 * opaque widget session token. Called once on load for a protected agent: the
 * bootstrap token is verified against the agent's signing secret, and on
 * success we mint a session token the widget then sends as `X-AUTH-TOKEN`.
 *
 * Any custom `ctx` claim from the bootstrap token (currently only
 * `playground`, set by the dashboard's Live Preview) is recorded in the
 * session meta so downstream gated endpoints can branch on it without
 * inspecting easily-spoofed HTTP signals.
 */
class AuthExchangeController
{
    public function __invoke(
        Request $request,
        WidgetVerification $widgetVerification,
        AgentSignInTokenService $signInTokens,
        WidgetAuthSessionService $sessions,
    ): JsonResponse {
        if (! $widgetVerification->verifyByAgentExternalId($request->header('X-AGENT-ID'), $request)) {
            return response()->json(['error' => 'Could not verify the agent'], 403);
        }

        $agent = current_agent_from_widget();

        $verified = $signInTokens->verifyWithClaims($request->input('token'), $agent);

        if (! $verified) {
            return response()->json(['error' => 'Invalid or expired token'], 401);
        }

        $meta = [];
        if (($verified['ctx'] ?? null) === AgentSignInTokenService::CTX_PLAYGROUND) {
            $meta['is_playground'] = true;
        }

        $session = $sessions->issue($verified['user'], $agent, $request, $meta);

        return response()->json([
            'auth_token' => $session['token'],
            'expires_at' => $session['expires_at']->toIso8601String(),
        ]);
    }
}
