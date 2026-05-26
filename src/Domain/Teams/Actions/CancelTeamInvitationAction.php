<?php

namespace Domain\Teams\Actions;

use App\Teams\Models\Team;

class CancelTeamInvitationAction
{
    public function execute(Team $team, int $invitationId): void
    {
        $invitation = $team->invitations()->findOrFail($invitationId);
        $invitation->delete();
    }
}
