<?php

namespace App\Http\Controllers\Teams;

use App\Teams\Models\Team;
use App\Auth\Models\User;
use App\Teams\Services\TeamService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeamInviteMemberController
{
    public function __construct(
        private TeamService $teamService
    ) {}

    public function __invoke(Request $request, Team $team)
    {
        /** @var User $user */
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

        /** @var User $teamOwner */
        $teamOwner = $team->owner;

        if (! $teamOwner->canAddTeamMember()) {
            return back()->withErrors([
                'email' => "You've reached your member limit ({$teamOwner->quota->members_limit}). Upgrade your plan to invite more members.",
            ]);
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
            $team->members()->attach($existingUser->id, ['role_id' => $role->id]);

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
}
