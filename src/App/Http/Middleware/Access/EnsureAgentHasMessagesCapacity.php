<?php

namespace App\Http\Middleware\Access;

use Closure;
use Domain\Billing\Services\UserCreditSystem;
use Illuminate\Http\Request;
use Inertia\Inertia;

class EnsureAgentHasMessagesCapacity
{
    public function handle(Request $request, Closure $next)
    {
        $agentUserId = $request->attributes->get('agent_user_id');

        if (! $agentUserId) {
            return Inertia::render('agents/chat/AgentNotFound');
        }

        // Token-primary: gate on the token budget. "Messages" stays a
        // display/advertising unit; the real allowance is metered in tokens.
        if (app(UserCreditSystem::class)->setUserId($agentUserId)->canSendMessages() === false) {
            return Inertia::render('agents/chat/AgentNoMessages');
        }

        return $next($request);
    }
}
