<?php

namespace App\Http\Controllers\Teams;

use App\Models\Team;
use Illuminate\Http\Request;

class TeamCancelInvitationController
{
    public function __invoke(Request $request, Team $team, int $invitationId)
    {
        $user = $request->user();

        if (! $user->ownsTeam($team)) {
            abort(403, 'Only the team owner can cancel invitations.');
        }

        $invitation = $team->invitations()->findOrFail($invitationId);
        $invitation->delete();

        return back()->with('success', 'Invitation cancelled.');
    }
}
