<?php

namespace App\Http\Middleware\Auth;

use App\Identity\Workspace\Services\AgentSignInTokenService;
use Closure;
use Domain\Agent\Models\Agent;
use Domain\Agent\Services\Agent as AgentService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs an end-user into an agent's chat from a `?token=` sign-in link.
 *
 * Sits in the chat middleware group AFTER the agent is resolved from the
 * subdomain and BEFORE EnsureChatIsAccessible. A token that resolves to the
 * current agent logs in its real (external) user on the web guard, then we
 * redirect to the same URL without the token (keeping it out of history). From
 * there everything behaves like any authenticated chat user — conversations,
 * leads, logout all just work.
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

        if (! is_string($token) || $token === '') {
            return $next($request);
        }

        $agent = $this->resolveAgent($request);

        if (! $agent) {
            return $next($request);
        }

        $user = $this->tokens->verify($token, $agent);

        if ($user !== null) {
            Auth::guard('web')->login($user);
        }

        if ($request->isMethod('GET')) {
            return redirect()->to($request->url());
        }

        return $next($request);
    }

    private function resolveAgent(Request $request): ?Agent
    {
        $slug = $request->agentSlug();

        if (! $slug) {
            return null;
        }

        return AgentService::resolveAgentFromSubdomain($slug);
    }
}
