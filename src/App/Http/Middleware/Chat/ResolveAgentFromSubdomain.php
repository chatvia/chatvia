<?php

namespace App\Http\Middleware\Chat;

use Closure;
use Domain\Agent\Services\AgentLocator;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response;

/**
 * First middleware of the hosted chat (`chat` group): works out which agent
 * the request is for. An explicit `?agent=` is remembered in the session (so
 * later requests without it stay on that agent), and an agent slug that does
 * not resolve shows the not-found page.
 *
 * The slug is kept in the `agent_slug` request attribute — not merged into the
 * input, where it would overwrite an `agent` field the client sent.
 */
class ResolveAgentFromSubdomain
{
    public function handle(Request $request, Closure $next): Response|InertiaResponse
    {
        $explicit = $request->query('agent');

        if (is_string($explicit) && $explicit !== '' && $request->hasSession()) {
            $request->session()->put('chat_agent', $explicit);
        }

        $slug = $request->agentSlug();

        if ($slug && ! AgentLocator::resolveAgentFromSubdomain($slug)) {
            return Inertia::render('agents/chat/AgentNotFound');
        }

        $request->attributes->set('agent_slug', $slug);

        return $next($request);
    }
}
