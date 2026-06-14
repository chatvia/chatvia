<?php

namespace App\Http\Controllers\Agent\Security;

use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Per-agent chat rate-limit page — caps how many messages the agent answers per
 * minute across all visitors, with an optional owner email when it trips. The
 * write endpoint is {@see UpdateAgentSecuritySettingsController}.
 */
class AgentRateLimitController
{
    public function __invoke(Request $request, Agent $agent): Response
    {
        abort_unless(has_permission_to_agent($agent, 'agent.identity.manage'), 404);

        $security = $agent->settings('security');

        return Inertia::render('agents/security/RateLimiting', [
            'agent' => $agent,
            'securitySettings' => [
                'chat_rate_limit_per_minute' => (int) $security->get('chat_rate_limit_per_minute', 20),
                'notify_owner_on_rate_limit' => (bool) $security->get('notify_owner_on_rate_limit', false),
            ],
        ]);
    }
}
