<?php

namespace Domain\Membership\Team\Actions\Invitations;

use Domain\Membership\Team\Actions\Members\InviteTeamMemberAction;
use Domain\Membership\Team\Events\TeamInvitationCreated;
use Domain\Membership\Team\Listeners\SendTeamInvitationEmail;
use Domain\Membership\Team\Models\Invitation;
use Domain\Membership\Team\Models\Team;
use RuntimeException;

/**
 * Persists a pending team invitation and announces it.
 *
 * The mail send lives in {@see SendTeamInvitationEmail}, so this Action only
 * writes the row and fires the event — side effects flow through events +
 * listeners, never an inline notification dispatch.
 *
 * The owner-side business gates (active subscription, seat limit) and the
 * already-a-member / direct-attach branches stay in
 * {@see InviteTeamMemberAction}; this
 * Action is the narrow "create the invitation record" step it delegates to.
 */
class CreateTeamInvitationAction
{
    public function execute(Team $team, string $email, string $roleCode): Invitation
    {
        $this->guardInvitation($team, $email, $roleCode);

        $role = $team->roles()->where('code', $roleCode)->firstOrFail();

        $invitation = $team->invitations()->create([
            'email' => $email,
            'role_id' => $role->id,
        ]);

        TeamInvitationCreated::dispatch($invitation, $email);

        return $invitation;
    }

    private function guardInvitation(Team $team, string $email, string $roleCode): void
    {
        if ($team->hasUserWithEmail($email)) {
            throw new RuntimeException('This user already belongs to the team.');
        }

        if (! $team->roles()->where('code', $roleCode)->exists()) {
            throw new RuntimeException("The role '{$roleCode}' does not exist for this team.");
        }
    }
}
