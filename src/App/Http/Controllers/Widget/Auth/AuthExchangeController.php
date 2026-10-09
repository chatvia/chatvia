<?php

namespace App\Http\Controllers\Widget\Auth;

use App\Http\Widget\WidgetAgent;
use App\Identity\Workspace\Services\AgentSignInTokenService;
use App\Identity\Workspace\Services\WidgetAuthSessionService;
use Domain\Agent\Models\Agent;
use Domain\Widget\Data\Auth\WidgetAuthTokenData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Exchanges a signed bootstrap token (the embed's `data-auth` value) for an
 * opaque widget session token. Called once on load for a protected agent: the
 * bootstrap token is checked against the agent's signing secret, and on
 * success a session token is minted that the widget then sends as
 * X-AUTH-TOKEN.
 *
 * A `ctx: playground` claim in the bootstrap token (set by the dashboard's
 * live preview) is stored with the session, so later endpoints can tell a
 * preview apart without trusting easily faked HTTP signals.
 */
class AuthExchangeController
{
    public function __invoke(
        Request $request,
        #[WidgetAgent] Agent $agent,
        AgentSignInTokenService $signInTokens,
        WidgetAuthSessionService $sessions,
    ): JsonResponse {
        $verified = $signInTokens->verifyWithClaims($request->string('token')->toString(), $agent);

        if (! $verified) {
            return response()->json(['error' => 'Invalid or expired token'], JsonResponse::HTTP_UNAUTHORIZED);
        }

        $meta = ($verified['ctx'] ?? null) === AgentSignInTokenService::CTX_PLAYGROUND
            ? ['is_playground' => true]
            : [];

        $session = $sessions->issue($verified['user'], $agent, $request, $meta);

        // A plain 200 (a Data object would answer a POST with 201).
        return response()->json(new WidgetAuthTokenData(
            auth_token: $session['token'],
            expires_at: $session['expires_at']->toIso8601String(),
        ));
    }
}
