<?php

namespace App\Http\Controllers\Teams;

use App\Data\Resources\Team\TeamData;
use App\Data\Resources\Team\TeamRoleData;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\Teams\TeamService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TeamController
{
    public function __construct(
        private TeamService $teamService
    ) {}

    public function inviteMember(Request $request, Team $team)
    {
        $user = $request->user();

        if (! $user->ownsTeam($team) && ! $user->hasTeamPermission($team, 'team.invite')) {
            abort(403, 'You do not have permission to invite members.');
        }

        $validRoles = $team->roles()->where('code', '!=', 'owner')->pluck('code')->toArray();

        $validated = $request->validate([
            'user_id' => ['nullable', 'integer', 'exists:users,id'],
            'email' => ['nullable', 'email', 'max:255', 'required_without:user_id'],
            'role' => ['required', 'string', Rule::in($validRoles)],
        ]);

        if (! $this->teamService->canInviteMembers($team)) {
            return back()->withErrors(['email' => 'You need an active subscription to invite team members.']);
        }

        if ($validated['user_id']) {
            $existingUser = User::findOrFail($validated['user_id']);

            if ($existingUser->managed_by_user_id !== $user->id) {
                return back()->withErrors(['user_id' => 'You can only add users you have invited.']);
            }

            if ($team->hasUser($existingUser)) {
                return back()->withErrors(['user_id' => 'This user is already a member of the team.']);
            }

            $role = $team->roles()->where('code', $validated['role'])->firstOrFail();
            $team->users()->attach($existingUser->id, ['role_id' => $role->id]);

            return back()->with('success', 'Member added successfully.');
        }

        if ($team->hasUserWithEmail($validated['email'])) {
            return back()->withErrors(['email' => 'This user is already a member of the team.']);
        }

        if ($team->invitations()->where('email', $validated['email'])->exists()) {
            return back()->withErrors(['email' => 'An invitation has already been sent to this email.']);
        }

        $this->teamService->inviteUser($team, $validated['email'], $validated['role']);

        return back()->with('success', 'Invitation sent successfully.');
    }

    public function cancelInvitation(Request $request, Team $team, int $invitationId)
    {
        $user = $request->user();

        if (! $user->ownsTeam($team)) {
            abort(403, 'Only the team owner can cancel invitations.');
        }

        $invitation = $team->invitations()->findOrFail($invitationId);
        $invitation->delete();

        return back()->with('success', 'Invitation cancelled.');
    }

    public function updateMemberRole(Request $request, Team $team, User $member)
    {
        $user = $request->user();

        if (! $user->ownsTeam($team)) {
            abort(403, 'Only the team owner can update member roles.');
        }

        if ($team->user_id === $member->id) {
            abort(403, 'Cannot change the owner\'s role.');
        }

        $validRoles = $team->roles()->where('code', '!=', 'owner')->pluck('code')->toArray();

        $validated = $request->validate([
            'role' => ['required', 'string', Rule::in($validRoles)],
        ]);

        $team->updateUser($member, $validated['role']);

        return back()->with('success', 'Member role updated successfully.');
    }

    public function removeMember(Request $request, Team $team, User $member)
    {
        $user = $request->user();

        if (! $user->ownsTeam($team)) {
            abort(403, 'Only the team owner can remove members.');
        }

        if ($team->user_id === $member->id) {
            abort(403, 'Cannot remove the team owner.');
        }

        $team->deleteUser($member);

        return back()->with('success', 'Member removed from team.');
    }

    public function switch(Request $request, Team $team)
    {
        $user = $request->user();

        if (! $user->switchTeam($team)) {
            abort(403, 'You do not have access to this team.');
        }

        return redirect()->back()->with('success', 'Switched to '.$team->name);
    }

    public function showCreateRole(Request $request, Team $team)
    {
        $user = $request->user();

        if (! $user->ownsTeam($team)) {
            abort(403, 'Only the team owner can create roles.');
        }

        return Inertia::render('teams/roles/Create', [
            'team' => TeamData::fromModel($team),
            'agents' => $this->teamService->teamAgents($team),
            'permissionsConfig' => $this->teamService->permissionsConfig(),
            'permissionGroups' => $this->teamService->permissionGroups(),
        ]);
    }

    public function showEditRole(Request $request, Team $team, int $roleId)
    {
        $user = $request->user();

        if (! $user->ownsTeam($team)) {
            abort(403, 'Only the team owner can edit roles.');
        }

        $roleModel = $team->roles()->with('permissions')->findOrFail($roleId);

        if ($roleModel->code === 'owner') {
            return redirect()->route('teams.show', $team)->with('error', 'Cannot edit the owner role.');
        }

        return Inertia::render('teams/roles/Edit', [
            'team' => TeamData::fromModel($team),
            'role' => TeamRoleData::fromModel($roleModel),
            'agents' => $this->teamService->teamAgents($team),
            'permissionsConfig' => $this->teamService->permissionsConfig(),
            'permissionGroups' => $this->teamService->permissionGroups(),
        ]);
    }

    public function createRole(Request $request, Team $team)
    {
        $user = $request->user();

        if (! $user->ownsTeam($team)) {
            abort(403, 'Only the team owner can create roles.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string'],
            'agent_ids' => ['array'],
            'agent_ids.*' => ['integer', 'exists:agents,id'],
        ]);

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

        $permissionModels = collect($validated['permissions'])->map(function ($permissionName) {
            return Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        });

        $role->syncPermissions($permissionModels);

        $agentIds = $validated['agent_ids'] ?? [];
        if (! $role->hasPermissionTo('agents.access-all') && ! empty($agentIds)) {
            $role->agents()->sync($agentIds);
        }

        return redirect()->route('teams.show', $team)->with('success', 'Role created successfully.');
    }

    public function updateRole(Request $request, Team $team, int $roleId)
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
            'agent_ids' => ['array'],
            'agent_ids.*' => ['integer', 'exists:agents,id'],
        ]);

        $roleModel->update([
            'name' => $validated['name'],
        ]);

        $permissionModels = collect($validated['permissions'])->map(function ($permissionName) {
            return Permission::firstOrCreate([
                'name' => $permissionName,
                'guard_name' => 'web',
            ]);
        });

        $roleModel->syncPermissions($permissionModels);

        $agentIds = $validated['agent_ids'] ?? [];
        if ($roleModel->hasPermissionTo('agents.access-all')) {
            $roleModel->agents()->detach();
        } else {
            $roleModel->agents()->sync($agentIds);
        }

        return redirect()->route('teams.show', $team)->with('success', 'Role updated successfully.');
    }

    public function deleteRole(Request $request, Team $team, int $roleId)
    {
        $user = $request->user();

        if (! $user->ownsTeam($team)) {
            abort(403, 'Only the team owner can delete roles.');
        }

        $roleModel = $team->roles()->findOrFail($roleId);

        if (in_array($roleModel->code, ['owner', 'admin'])) {
            abort(403, 'Cannot delete the owner or admin role.');
        }

        $membersWithRole = $team->users()->wherePivot('role_id', $roleModel->id)->count();
        if ($membersWithRole > 0) {
            return back()->with('error', 'Cannot delete this role. '.$membersWithRole.' member(s) are assigned to it.');
        }

        $roleModel->delete();

        return back()->with('success', 'Role deleted successfully.');
    }
}
