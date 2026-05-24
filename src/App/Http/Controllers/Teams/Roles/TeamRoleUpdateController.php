<?php

namespace App\Http\Controllers\Teams\Roles;

use Domain\Agents\Models\Agent;
use App\Teams\Models\Permission;
use App\Teams\Models\Team;
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
            'permissions' => ['nullable', 'array'],
            'permissions.*' => ['string'],
            'all_agents' => ['boolean'],
            'agent_ids' => ['exclude_if:all_agents,true', 'required', 'array', 'min:1'],
            'agent_ids.*' => ['string', 'exists:agents,external_id'],
        ]);

        $allAgents = $validated['all_agents'] ?? false;
        $externalIds = $validated['agent_ids'] ?? [];

        // Convert external_ids to actual agent IDs
        $agentIds = $allAgents ? [] : Agent::whereIn('external_id', $externalIds)->pluck('id')->toArray();

        if (! $allAgents && count($agentIds) !== count($externalIds)) {
            return back()->withErrors(['agent_ids' => 'One or more selected agents are invalid.']);
        }

        $roleModel->update([
            'name' => $validated['name'],
        ]);

        // Handle permissions - include agents.access-all if all_agents is true
        $permissions = $validated['permissions'] ?? [];
        if ($allAgents) {
            $permissions[] = 'agents.access-all';
        }

        if (! empty($permissions)) {
            $permissionModels = collect($permissions)->unique()->map(function ($permissionName) {
                return Permission::firstOrCreate([
                    'name' => $permissionName,
                    'guard_name' => 'web',
                ]);
            });

            $roleModel->syncPermissions($permissionModels);
        } else {
            // Clear all permissions if none provided
            $roleModel->syncPermissions([]);
        }

        // Sync agents based on all_agents flag
        if ($allAgents) {
            $roleModel->agents()->detach();
        } else {
            $roleModel->agents()->sync($agentIds);
        }

        return redirect()->route('teams.show', $team)->with('success', 'Role updated successfully.');
    }
}
