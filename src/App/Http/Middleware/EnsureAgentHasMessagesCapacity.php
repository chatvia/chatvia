<?php

namespace App\Http\Middleware;

use App\Billing\Services\UserCreditSystem;
use Closure;
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

        if (app(UserCreditSystem::class)->setUserId($agentUserId)->canSendMessages() === false) {
            return Inertia::render('agents/chat/AgentNoMessages');
        }

        return $next($request);
    }
}
