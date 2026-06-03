<?php

namespace Domain\Membership\Team\Actions\Invitations;

use Domain\Membership\Team\Models\Team;

class CancelTeamInvitationAction
{
    public function execute(Team $team, int $invitationId): void
    {
        $invitation = $team->invitations()->findOrFail($invitationId);
        $invitation->delete();
    }
}
