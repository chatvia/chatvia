<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
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

        $teams = $user->allTeams()->map(function ($team) use ($user) {
            return [
                'id' => $team->id,
                'name' => $team->name,
                'owner' => [
                    'id' => $team->owner->id,
                    'name' => $team->owner->name,
                    'email' => $team->owner->email,
                ],
                'is_owner' => $user->ownsTeam($team),
                'is_primary' => $user->team_id === $team->id,
                'role' => $user->teamRole($team)?->code ?? 'owner',
                'members_count' => $team->allUsers()->count(),
                'created_at' => $team->created_at->toDateTimeString(),
            ];
        })->values()->all();

        return Inertia::render('settings/teams/Index', [
            'teams' => $teams,
            'availableRoles' => $this->teamService->getDefaultRoles(),
        ]);
    }

    public function show(Request $request, Team $team)
    {
        $user = $request->user();

        // Ensure user belongs to this team
        if (! $user->belongsToTeam($team) && ! $user->ownsTeam($team)) {
            abort(403, 'You do not have access to this team.');
        }

        $members = $team->allUsers()->map(function ($member) use ($team) {
            $role = $team->userRole($member);

            return [
                'id' => $member->id,
                'name' => $member->name,
                'email' => $member->email,
                'role' => $role?->code ?? 'owner',
                'role_name' => $role?->name ?? 'Owner',
                'is_owner' => $team->user_id === $member->id,
                'joined_at' => $member->pivot?->created_at?->toDateTimeString(),
            ];
        });

        $roles = $team->roles()->with('permissions')->get()->map(
            fn ($role) => $this->teamService->formatRoleForFrontend($role, $team)
        )->values()->all();

        $agents = $team->agents()->select('id', 'name')->get()->map(fn ($agent) => [
            'id' => $agent->id,
            'name' => $agent->name,
        ])->values()->all();

        $pendingInvitations = $team->invitations()->with('role')->get()->map(fn ($invitation) => [
            'id' => $invitation->id,
            'email' => $invitation->email,
            'role' => $invitation->role?->code,
            'role_name' => $invitation->role?->name,
            'created_at' => $invitation->created_at->toDateTimeString(),
        ]);

        return Inertia::render('settings/teams/Show', [
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
                'owner' => [
                    'id' => $team->owner->id,
                    'name' => $team->owner->name,
                    'email' => $team->owner->email,
                ],
            ],
            'members' => $members,
            'roles' => $roles,
            'agents' => $agents,
            'pendingInvitations' => $pendingInvitations,
            'isOwner' => $user->ownsTeam($team),
            'userRole' => $user->teamRole($team)?->code ?? 'owner',
            'availableRoles' => $this->teamService->getDefaultRoles(),
            'availablePermissions' => $this->teamService->getAvailablePermissions(),
            'permissionGroups' => $this->teamService->getPermissionGroups(),
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

        // Create default roles
        $this->teamService->createDefaultRoles($team);

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

        // Prevent deleting the user's primary team
        if ($user->team_id === $team->id) {
            return back()->with('error', 'You cannot delete your primary team.');
        }

        // Prevent deleting the user's only team
        if ($user->ownedTeams()->count() <= 1) {
            return back()->with('error', 'You cannot delete your only team. Create another team first.');
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

        // Get valid role codes from the team's actual roles (excluding owner)
        $validRoles = $team->roles()->where('code', '!=', 'owner')->pluck('code')->toArray();

        $validated = $request->validate([
            'email' => ['required', 'email', 'max:255'],
            'role' => ['required', 'string', Rule::in($validRoles)],
        ]);

        // Check if user is already a member
        if ($team->hasUserWithEmail($validated['email'])) {
            return back()->withErrors(['email' => 'This user is already a member of the team.']);
        }

        // Check if invitation already exists
        if ($team->invitations()->where('email', $validated['email'])->exists()) {
            return back()->withErrors(['email' => 'An invitation has already been sent to this email.']);
        }

        // Check if owner has an active subscription
        if (! $this->teamService->canInviteMembers($team)) {
            return back()->withErrors(['email' => 'You need an active subscription to invite team members.']);
        }

        // Use our custom service to send invitation with our notification
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

        // Get valid role codes from the team's actual roles (excluding owner)
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

        // Ensure user belongs to this team or owns it
        if (! $user->belongsToTeam($team) && ! $user->ownsTeam($team)) {
            abort(403, 'You do not have access to this team.');
        }

        // Update user's current team
        $user->switchTeam($team);

        return back()->with('success', 'Switched to '.$team->name);
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
            'all_agents' => ['boolean'],
            'agent_ids' => ['array'],
            'agent_ids.*' => ['integer', 'exists:agents,id'],
        ]);

        // Generate a code from the name
        $code = strtolower(str_replace(' ', '_', $validated['name']));

        // Check if role code already exists
        if ($team->roles()->where('code', $code)->exists()) {
            return back()->withErrors(['name' => 'A role with this name already exists.']);
        }

        $role = $team->addRole(
            $code,
            $validated['permissions'],
            $validated['name']
        );

        // Update agent access
        $allAgents = $validated['all_agents'] ?? false;
        $agentIds = $validated['agent_ids'] ?? [];
        $this->teamService->updateRoleAgentAccess($role, $allAgents, $agentIds);

        return back()->with('success', 'Role created successfully.');
    }

    public function updateRole(Request $request, Team $team, int $roleId)
    {
        $user = $request->user();

        if (! $user->ownsTeam($team)) {
            abort(403, 'Only the team owner can update roles.');
        }

        $roleModel = $team->roles()->findOrFail($roleId);

        // Prevent updating owner role
        if ($roleModel->code === 'owner') {
            abort(403, 'Cannot modify the owner role.');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string'],
            'all_agents' => ['boolean'],
            'agent_ids' => ['array'],
            'agent_ids.*' => ['integer', 'exists:agents,id'],
        ]);

        // Use the package's built-in updateRole method
        $team->updateRole(
            $roleModel->code,
            $validated['permissions'],
            $validated['name']
        );

        // Update agent access
        $allAgents = $validated['all_agents'] ?? false;
        $agentIds = $validated['agent_ids'] ?? [];
        $this->teamService->updateRoleAgentAccess($roleModel, $allAgents, $agentIds);

        return back()->with('success', 'Role updated successfully.');
    }

    public function deleteRole(Request $request, Team $team, int $roleId)
    {
        $user = $request->user();

        if (! $user->ownsTeam($team)) {
            abort(403, 'Only the team owner can delete roles.');
        }

        $roleModel = $team->roles()->findOrFail($roleId);

        // Prevent deleting owner or admin roles
        if (in_array($roleModel->code, ['owner', 'admin'])) {
            abort(403, 'Cannot delete the owner or admin role.');
        }

        // Check if any members have this role
        $membersWithRole = $team->users()->wherePivot('role_id', $roleModel->id)->count();
        if ($membersWithRole > 0) {
            return back()->with('error', 'Cannot delete this role. '.$membersWithRole.' member(s) are assigned to it.');
        }

        $roleModel->delete();

        return back()->with('success', 'Role deleted successfully.');
    }
}
