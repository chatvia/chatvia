<?php

namespace App\Http\Controllers\Team\Member;

use App\Team\Models\Team;
use Domain\Team\Actions\Invitations\CancelTeamInvitationAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TeamCancelInvitationController
{
    public function __invoke(
        Request $request,
        Team $team,
        int $invitationId,
        CancelTeamInvitationAction $cancel,
    ): RedirectResponse {
        $user = $request->user();

        if (! $user->ownsTeam($team)) {
            abort(403, 'Only the team owner can cancel invitations.');
        }

        $cancel->execute($team, $invitationId);

        return back()->with('success', 'Invitation cancelled.');
    }
}
