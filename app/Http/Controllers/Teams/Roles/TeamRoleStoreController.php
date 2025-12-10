<?php

namespace App\Http\Controllers\Teams\Roles;

use App\Models\Agent;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use Illuminate\Http\Request;

class TeamRoleStoreController
{
    public function __invoke(Request $request, Team $team)
    {
        $user = $request->user();

        //        if (! $user->ownsTeam($team)) {
        //            return back()->withErrors(['teams' => 'Only the team owner can create roles.']);
        //        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'permissions' => ['required', 'array', 'min:1'],
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

        $code = strtolower(str_replace(' ', '_', $validated['name']));

        if ($team->roles()->where('code', $code)->exists()) {
            return back()->withErrors(['name' => 'A role with this name already exists.']);
        }

        $role = Role::create([
            'name' => $validated['name'],
            'code' => $code,
            'team_id' => $team->id,
            'guard_name' => 'web',
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

        $role->syncPermissions($permissionModels);

        // Sync agents only if not accessing all agents
        if (! $allAgents) {
            $role->agents()->sync($agentIds);
        }

        return redirect()->route('teams.show', $team)->with('success', 'Role created successfully.');
    }
}
