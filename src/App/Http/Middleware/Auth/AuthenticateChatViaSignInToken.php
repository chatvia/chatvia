<?php

namespace App\Http\Middleware\Auth;

use App\Http\Chat\HostedChatAgent;
use App\Http\Chat\HostedChatViewer;
use App\Identity\Workspace\Services\AgentSignInTokenService;
use Closure;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs an end-user into an agent's chat from a `?token=` sign-in link.
 *
 * Sits in the chat middleware group (the agent is already resolved from the
 * `/chat/{agentSlug}` path) and BEFORE EnsureChatIsAccessible. A token that
 * resolves to the current agent signs its chatter or staff account in to the
 * chat ({@see HostedChatViewer}, never the dashboard), then a GET is redirected
 * to the same URL without the token (keeping it out of the browser history).
 * From there the visitor is an ordinary signed-in chat user.
 *
 * The token is multi-use until its TTL, so it survives browser prefetch /
 * double requests. An expired / unknown token (or one minted for another agent)
 * is ignored — the redirect strips it and the normal public/private rules take
 * over. No token at all is fine too.
 */
class AuthenticateChatViaSignInToken
{
    public function __construct(
        private readonly AgentSignInTokenService $tokens,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        $token = $request->query('token');

        if (! is_string($token) || blank($token)) {
            return $next($request);
        }

        $agent = HostedChatAgent::from($request);

        if (! $agent instanceof Agent) {
            return $next($request);
        }

        $account = $this->tokens->verify($token, $agent);

        if ($account !== null) {
            HostedChatViewer::signIn($account);
        }

        if ($request->isMethod('GET')) {
            return redirect()->to($request->url());
        }

        return $next($request);
    }
}
