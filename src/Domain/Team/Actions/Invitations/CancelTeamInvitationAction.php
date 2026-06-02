<?php

namespace Domain\Team\Actions\Invitations;

use App\Team\Models\Team;

class CancelTeamInvitationAction
{
    public function execute(Team $team, int $invitationId): void
    {
        $invitation = $team->invitations()->findOrFail($invitationId);
        $invitation->delete();
    }
}
