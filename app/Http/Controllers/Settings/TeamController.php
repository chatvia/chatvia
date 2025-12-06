<?php

namespace App\Http\Controllers\Settings;

use App\Data\Resources\Team\ManagedUserData;
use App\Data\Resources\Team\TeamData;
use App\Data\Resources\Team\TeamInvitationData;
use App\Data\Resources\Team\TeamMemberData;
use App\Data\Resources\Team\TeamRoleData;
use App\Http\Controllers\Controller;
use App\Models\Permission;
use App\Models\Role;
use App\Models\Team;
use App\Models\User;
use App\Services\Teams\TeamService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class TeamController extends Controller
{
    public function __construct(
        private TeamService $teamService
    ) {}

    public function index(Request $request)
    {
        $user = $request->user();

        $teams = $user->teams->map(function ($team) use ($user) {
            return [
                'id' => $team->id,
                'name' => $team->name,
                'owner' => [
                    'id' => $team->owner->id,
                    'name' => $team->owner->name,
                    'email' => $team->owner->email,
                ],
                'is_owner' => $user->ownsTeam($team),
                'is_primary' => $user->isPrimaryTeam($team),
                'role' => $user->teamRole($team)?->code ?? 'owner',
                'members_count' => $team->allUsers()->count(),
                'created_at' => $team->created_at->toDateTimeString(),
            ];
        })->values()->all();

        return Inertia::render('settings/teams/Index', [
            'teams' => $teams,
            'availableRoles' => $this->teamService->defaultRoles(),
        ]);
    }

    public function show(Request $request, Team $team)
    {
        $user = $request->user();

        if (! $user->belongsToTeam($team) && ! $user->ownsTeam($team)) {
            abort(403, 'You do not have access to this team.');
        }

        $members = $team->allUsers()->map(
            fn ($member) => TeamMemberData::fromUser($member, $team)
        );

        $roles = $this->teamService->teamRoles($team);

        $agents = $this->teamService->teamAgents($team);

        $pendingInvitations = $team->invitations()
            ->with('role')
            ->get()
            ->map(fn ($invitation) => TeamInvitationData::fromModel($invitation));

        $currentMemberIds = $team->allUsers()->pluck('id')->toArray();
        $managedUsers = $user->managedUsers()
            ->whereNotIn('id', $currentMemberIds)
            ->get()
            ->map(fn ($managedUser) => ManagedUserData::fromModel($managedUser));

        return Inertia::render('settings/teams/Show', [
            'team' => TeamData::fromModel($team),
            'members' => $members,
            'roles' => $roles,
            'agents' => $agents,
            'pendingInvitations' => $pendingInvitations,
            'managedUsers' => $managedUsers,
            'isOwner' => $user->ownsTeam($team),
            'userRole' => $user->teamRole($team)?->code ?? 'owner',
            'availableRoles' => $this->teamService->defaultRoles(),
            'permissionsConfig' => $this->teamService->permissionsConfig(),
            'permissionGroups' => $this->teamService->permissionGroups(),
        ]);
    }

    public function store(Request $request)
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $user = $request->user();

        $team = new Team;
        $team->name = $validated['name'];
        $team->user_id = $user->id;
        $team->save();

        $this->teamService->createDefaultRoles($team);

        $ownerRole = $team->roles()->where('code', 'owner')->first();
        if ($ownerRole) {
            $team->users()->attach($user->id, [
                'role_id' => $ownerRole->id,
                'is_owner' => true,
            ]);
        }

        return redirect()->route('teams.show', $team)
            ->with('success', 'Team created successfully.');
    }

    public function update(Request $request, Team $team)
    {
        $user = $request->user();

        if (! $user->ownsTeam($team)) {
            abort(403, 'Only the team owner can update the team.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        $team->update($validated);

        return back()->with('success', 'Team updated successfully.');
    }

    public function destroy(Request $request, Team $team)
    {
        $user = $request->user();

        if (! $user->ownsTeam($team)) {
            abort(403, 'Only the team owner can delete the team.');
        }

        if ($user->isPrimaryTeam($team)) {
            return back()->with('error', 'You cannot delete your primary team.');
        }

        if ($user->ownedTeams()->count() <= 1) {
            return back()->with('error', 'You cannot delete your only team. Create another team first.');
        }

        if ($team->agents()->exists()) {
            return back()->with('error', 'You cannot delete a team that has agents. Please delete or move all agents first.');
        }

        if ($team->members()->exists()) {
            return back()->with('error', 'You cannot delete a team that has members. Please remove all members first.');
        }

        $team->delete();

        return redirect()->route('teams.index')
            ->with('success', 'Team deleted successfully.');
    }

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

        if (! $user->belongsToTeam($team) && ! $user->ownsTeam($team)) {
            abort(403, 'You do not have access to this team.');
        }

        session(['current_team_id' => $team->id]);

        return redirect()->back()->with('success', 'Switched to '.$team->name);
    }

    public function leave(Request $request, Team $team)
    {
        $user = $request->user();

        if ($user->ownsTeam($team)) {
            abort(403, 'Team owners cannot leave their own team. Transfer ownership first or delete the team.');
        }

        $team->deleteUser($user);

        return redirect()->route('teams.index')
            ->with('success', 'You have left the team.');
    }

    public function showCreateRole(Request $request, Team $team)
    {
        $user = $request->user();

        if (! $user->ownsTeam($team)) {
            abort(403, 'Only the team owner can create roles.');
        }

        return Inertia::render('settings/teams/roles/Create', [
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

        return Inertia::render('settings/teams/roles/Edit', [
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
