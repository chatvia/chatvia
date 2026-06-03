<?php

namespace App\Http\Controllers\Agent\Team;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
use Domain\Billing\Models\UserQuota;
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
            'team_collaboration' => $quota->workspace_users_limit > 0,
            'workspace_users_limit' => $quota->workspace_users_limit,
            'workspace_users_count' => $user->workspaceUsersCount(),
        ]);
    }
}
