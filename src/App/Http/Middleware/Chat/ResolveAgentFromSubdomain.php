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

        $request->merge([
            'agent' => $slug,
        ]);

        return $next($request);
    }
}
