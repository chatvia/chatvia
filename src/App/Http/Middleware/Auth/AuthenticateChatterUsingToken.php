<?php

namespace App\Http\Middleware\Auth;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatViewer;
use App\Http\Chat\PendingChatSignIn;
use App\Identity\Chatter\Services\ChatterAuthTokens;
use Closure;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Signs a user in to an agent's hosted chat from a `?auth_token=` URL.
 *
 * Consumes a one-time token minted by ChatterAuthTokens for this agent:
 * a token that resolves signs its chatter or staff account in to the chat
 * ({@see HostedChatViewer}, never the dashboard) and is used up at once — strictly single-use, unlike the
 * multi-use `?token=` sign-in-link flow handled by
 * AuthenticateChatViaSignInToken, which runs right after this middleware in
 * the chat group.
 *
 * Either way the request is redirected to the same address without the token,
 * so the spent token never lingers in the address bar or the history. When
 * the browser is already signed in to the chat as someone else, the person
 * confirms the switch first ({@see PendingChatSignIn}); e-mailed magic links
 * end here too, so they get the same step.
 */
class AuthenticateChatterUsingToken
{
    public function __construct(
        private readonly ChatterAuthTokens $authTokens
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
            $account = $this->authTokens->consume($request->string('auth_token')->toString(), $agent);

            // The token is spent either way; someone else signed in confirms
            // the switch first.
            if ($account !== null && PendingChatSignIn::wouldReplace($account)) {
                return PendingChatSignIn::hold($request, $account, $agent);
            }

            if ($account !== null) {
                HostedChatViewer::signIn($account);
            }
        }

        return redirect($request->fullUrlWithoutQuery('auth_token'));
    }
}
