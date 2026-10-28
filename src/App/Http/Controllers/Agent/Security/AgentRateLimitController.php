<?php

namespace App\Http\Controllers\Agent\Security;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Security\Data\AgentRateLimitSettingsData;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Per-agent chat rate-limit page — caps how many messages the agent answers per
 * minute across all visitors, with an optional owner email when it trips. The
 * write endpoint is {@see UpdateAgentSecuritySettingsController}. Gated by
 * `agent.permission:agent.identity.manage` on the route.
 */
class AgentRateLimitController
{
    public function __invoke(Agent $agent): Response
    {
        return Inertia::render('dashboard/agents/pages/security/RateLimiting', [
            'agent' => AgentResourceData::fromModel($agent),
            'securitySettings' => AgentRateLimitSettingsData::fromAgent($agent),
        ]);
    }
}
