<?php

namespace App\Http\Controllers\Agents\Team;

use Domain\Agents\Data\Resources\AgentResourceData;
use Domain\Agents\Models\Agent;
use App\Billing\Models\UserQuota;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentTeamController
{
    public function __invoke(Request $request, Agent $agent)
    {
        if (! $agent->isStandalone()) {
            return back()->with('error', 'Agent is not a knowledge base!');
        }

        $user = $request->user();

        abort_if(
            boolean: $user->cannot('view', $agent),
            code: 404,
        );

        /** @var UserQuota $quota */
        $quota = $user->quota;

        return Inertia::render('agents/Team', [
            'agent' => AgentResourceData::fromModel($agent),
            'team_collaboration' => $user->capabilityAllows('team_collaboration'),
            'members_limit' => $quota->members_limit,
        ]);
    }
}
