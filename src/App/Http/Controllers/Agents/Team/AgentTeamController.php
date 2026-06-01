<?php

namespace App\Http\Controllers\Agents\Team;

use App\Billing\Models\UserQuota;
use Domain\Agents\Data\Resources\Account\AgentResourceData;
use Domain\Agents\Models\Agent;
use Illuminate\Http\Request;
use Inertia\Inertia;

class AgentTeamController
{
    public function __invoke(Request $request, Agent $agent)
    {
        $user = $request->user();

        abort_if(
            boolean: $user->cannot('view', $agent),
            code: 404,
        );

        // effectiveUser()->quota fans out to (workspace, quota) for employees/
        // chatters — preload both legs in one go.
        $user->loadMissing(['workspace.quota', 'quota']);

        /** @var UserQuota $quota */
        $quota = $user->quota;

        return Inertia::render('agents/Team', [
            'agent' => AgentResourceData::fromModel($agent),
            'team_collaboration' => $quota->members_limit > 0,
            'members_limit' => $quota->members_limit,
        ]);
    }
}
