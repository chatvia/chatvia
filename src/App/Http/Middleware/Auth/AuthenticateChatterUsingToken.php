<?php

namespace App\Http\Middleware\Auth;

use App\Identity\Chatter\Services\ChatterAuthTokenService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs a chatter into an agent's chat from a `?auth_token=` URL.
 *
 * Consumes a one-time auth token minted by ChatterAuthTokenService (cache-backed,
 * 60-minute TTL). A token that resolves logs its chatter in on the web guard and
 * is invalidated immediately — strictly single-use, unlike the multi-use
 * `?token=` sign-in-link flow handled by AuthenticateChatViaSignInToken, which
 * runs right after this middleware in the chat group. Either way the request is
 * redirected to the clean chat home so the spent token never lingers in the URL.
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
        if ($request->filled('auth_token')) {
            $authToken = $request->input('auth_token');

            // A valid (not-yet-consumed, unexpired) token signs the chatter in.
            // An invalid/expired/already-used token is a no-op here — the
            // downstream chat middleware will then route the still-anonymous
            // visitor to login.
            if ($cachedAuthToken = $this->authTokenService->getTokenData($authToken)) {
                Auth::guard('web')->loginUsingId($cachedAuthToken['user_id']);

                $this->authTokenService->invalidateToken($authToken);
            }

            // Always redirect away from the auth_token URL, whether or not the
            // token resolved. This strips the single-use token from the address
            // bar (so a refresh/back doesn't replay a dead token) and lands the
            // visitor on the clean chat home.
            //
            // Relative redirect: the request already arrived on the agent's
            // subdomain, and with SESSION_DOMAIN=null the session cookie is
            // scoped to that exact host. Redirecting to the absolute
            // route('chat.home') would jump to the apex domain, where the cookie
            // isn't sent and no agent resolves — bouncing the user to login.
            // Staying on the current host keeps both intact. Same pattern as
            // ChatLoginController.
            return redirect(route('chat.home', absolute: false));
        }

        return $next($request);
    }
}
