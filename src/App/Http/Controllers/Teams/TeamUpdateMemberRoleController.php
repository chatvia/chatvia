<?php

namespace App\Http\Controllers\Teams;

use App\Auth\Models\User;
use App\Teams\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class TeamUpdateMemberRoleController
{
    public function __invoke(Request $request, Team $team, User $member)
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

        $team->updateMember($member, $validated['role']);

        return back()->with('success', 'Member role updated successfully.');
    }
}
