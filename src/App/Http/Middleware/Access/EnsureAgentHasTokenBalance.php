<?php

namespace App\Http\Middleware\Access;

use Closure;
use Domain\Billing\Services\UserTokenSystem;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EnsureAgentHasTokenBalance
{
    public function handle(Request $request, Closure $next)
    {
        $agentUserId = $request->attributes->get('agent_user_id');

        if (! $agentUserId) {
            return Inertia::render('agents/chat/AgentNotFound');
        }

        // Token-primary gate: block only when the workspace has no positive token
        // balance. "Messages" is a display/advertising unit; the real allowance
        // is metered in tokens.
        if (app(UserTokenSystem::class)->setUserId($agentUserId)->hasTokenBalance() === false) {
            return Inertia::render('agents/chat/AgentNoTokens');
        }

        return $next($request);
    }
}
