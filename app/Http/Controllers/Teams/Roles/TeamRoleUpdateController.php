<?php

namespace App\Http\Controllers\Teams\Roles;

use App\Models\Permission;
use App\Models\Team;
use Illuminate\Http\Request;

class TeamRoleUpdateController
{
    public function __invoke(Request $request, Team $team, int $roleId)
    {
        $user = $request->user();

        if (! $user->ownsTeam($team)) {
            abort(403, 'Only the team owner can update roles.');
        }

        $roleModel = $team->roles()->findOrFail($roleId);

        if ($roleModel->code === 'owner') {
            abort(403, 'Cannot modify the owner role.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string'],
            'all_agents' => ['boolean'],
            'agent_ids' => ['required_unless:all_agents,true', 'array', 'min:1'],
            'agent_ids.*' => ['integer', 'exists:agents,id'],
        ]);

        $allAgents = $validated['all_agents'] ?? false;
        $agentIds = $validated['agent_ids'] ?? [];

        $roleModel->update([
            'name' => $validated['name'],
        ]);

        // Handle permissions - include agents.access-all if all_agents is true
        $permissions = $validated['permissions'];
        if ($allAgents) {
            $permissions[] = 'agents.access-all';
        }

        $permissionModels = collect($permissions)->unique()->map(function ($permissionName) {
            return Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        });

        $roleModel->syncPermissions($permissionModels);

        // Sync agents based on all_agents flag
        if ($allAgents) {
            $roleModel->agents()->detach();
        } else {
            $roleModel->agents()->sync($agentIds);
        }

        return redirect()->route('teams.show', $team)->with('success', 'Role updated successfully.');
    }
}
