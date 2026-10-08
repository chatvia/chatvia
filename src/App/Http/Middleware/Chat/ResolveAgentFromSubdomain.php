<?php

namespace App\Http\Middleware\Chat;

use Closure;
use Domain\Agent\Services\AgentLocator;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ResolveAgentFromSubdomain
{
    public function handle(Request $request, Closure $next)
    {
        $explicit = $request->query('agent');

        if (is_string($explicit) && $explicit !== '' && $request->hasSession()) {
            $request->session()->put('chat_agent', $explicit);
        }

        $slug = $request->agentSlug();

        if ($slug) {
            if (! AgentLocator::resolveAgentFromSubdomain($slug)) {
                return Inertia::render('agents/chat/AgentNotFound');
            }
        }

        // Keep the resolved slug on the request attributes, not in the input:
        // merging it into the input would overwrite an `agent` field the
        // client sent in the body or query.
        $request->attributes->set('agent_slug', $slug);

        return $next($request);
    }
}
