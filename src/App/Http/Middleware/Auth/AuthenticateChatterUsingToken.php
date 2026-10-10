<?php

namespace App\Http\Middleware\Auth;

use App\Http\Chat\HostedChatAgent;
use App\Identity\Chatter\Services\ChatterAuthTokenService;
use Closure;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs a user in to an agent's hosted chat from a `?auth_token=` URL.
 *
 * Consumes a one-time token minted by ChatterAuthTokenService for this agent:
 * a token that resolves signs its user in on the chat guard (never the
 * dashboard) and is used up at once — strictly single-use, unlike the
 * multi-use `?token=` sign-in-link flow handled by
 * AuthenticateChatViaSignInToken, which runs right after this middleware in
 * the chat group.
 *
 * Either way the request is redirected to the same address without the token,
 * so the spent token never lingers in the address bar or the history.
 */
class AuthenticateChatterUsingToken
{
    public function __construct(
        private readonly ChatterAuthTokenService $authTokenService
    ) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->filled('auth_token')) {
            return $next($request);
        }

        $agent = HostedChatAgent::from($request);

        // An invalid, expired, used or foreign token is a no-op here: the
        // chat's access rules then send a still-anonymous visitor to login.
        if ($agent instanceof Agent) {
            $userId = $this->authTokenService->consume($request->string('auth_token')->toString(), $agent);

            if ($userId !== null) {
                Auth::guard()->loginUsingId($userId);
            }
        }

        return redirect($request->fullUrlWithoutQuery('auth_token'));
    }
}
