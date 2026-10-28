<?php

declare(strict_types=1);

namespace App\Http\Controllers\Agent\Security;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The agent's Security → Blocked page: who cannot chat with the agent. The
 * page loads its lists itself ({@see ListAgentBlocksController}), so it only
 * needs the agent. Gated by `agent.permission:agent.guardrails.view` on the
 * route, like the Guardrails page where people are blocked.
 */
class AgentBlockedController
{
    public function __invoke(Agent $agent): Response
    {
        return Inertia::render('dashboard/agents/pages/security/Blocked', [
            'agent' => AgentResourceData::fromModel($agent),
        ]);
    }
}
