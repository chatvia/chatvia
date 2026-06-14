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

        // Billing is message-based: gate on the workspace owner's cached
        // can-send-messages flag (refreshed from the DB on cache miss, after
        // each consumption, and every 5 minutes by app:cache-message-usage).
        if (app(UserCreditSystem::class)->forWorkspace($agentUserId)->canSendMessages() === false) {
            return Inertia::render('agents/chat/AgentNoMessages');
        }

        return $next($request);
    }
}
