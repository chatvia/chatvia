<?php

namespace Domain\Membership\Team\Actions\Invitations;

use Domain\Membership\Team\Actions\Members\InviteTeamMemberAction;
use Domain\Membership\Team\Events\TeamInvitationCreated;
use Domain\Membership\Team\Listeners\SendTeamInvitationEmail;
use Domain\Membership\Team\Models\Invitation;
use Domain\Membership\Team\Models\Team;
use Support\Exceptions\DomainValidationException;

/**
 * Persists a pending team invitation and announces it.
 *
 * The mail send lives in {@see SendTeamInvitationEmail}, so this Action only
 * writes the row and fires the event — side effects flow through events +
 * listeners, never an inline notification dispatch.
 *
 * The already-a-member / direct-attach branches stay in
 * {@see InviteTeamMemberAction}; this Action is the narrow "create the
 * invitation record" step it delegates to.
 */
class CreateTeamInvitationAction
{
    /**
     * @throws DomainValidationException when the email already belongs to the team or the role does not exist
     */
    public function execute(Team $team, string $email, string $roleCode): Invitation
    {
        // Stored lower-case so the accept step and the `[team_id, email]`
        // unique index see one spelling per address.
        $email = strtolower(trim($email));

        if ($team->hasUserWithEmail($email)) {
            throw new DomainValidationException('email', __('This user already belongs to the team.'));
        }

        $role = $team->roles()->where('code', $roleCode)->first()
            ?? throw new DomainValidationException('role', __("The role ':role' does not exist for this team.", ['role' => $roleCode]));

        $invitation = $team->invitations()->create([
            'email' => $email,
            'role_id' => $role->id,
        ]);

        TeamInvitationCreated::dispatch($invitation, $email);

        return $invitation;
    }
}
