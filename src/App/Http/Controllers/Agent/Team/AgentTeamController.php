<?php

namespace App\Http\Controllers\Agent\Team;

use Domain\Agent\Data\Resources\Account\AgentResourceData;
use Domain\Agent\Models\Agent;
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

        // workspace()->quota fans out to (workspace, quota) for employees/
        // chatters — preload both legs in one go.
        $user->loadMissing(['parentWorkspace.quota', 'quota']);

        $workspaceUsersLimit = $user->quotaLimit('workspace_users_limit');

        return Inertia::render('agents/Team', [
            'agent' => AgentResourceData::fromModel($agent),
            'team_collaboration' => $workspaceUsersLimit > 0,
            'workspace_users_limit' => $workspaceUsersLimit,
            'workspace_users_count' => $user->workspaceUsersCount(),
        ]);
    }
}
