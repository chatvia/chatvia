<?php

declare(strict_types=1);

namespace App\Http\Resources\Invitations;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Invitation;
use Domain\Membership\Team\Models\Team;
use Illuminate\Support\Facades\URL;

/**
 * Props of the team-invitation landing page: the invitation, who sent it,
 * the role it grants, and the signed accept URL.
 */
final readonly class TeamInvitationPageProps
{
    /**
     * @return array<string, mixed>
     */
    public function for(Invitation $invitation): array
    {
        /** @var Team $team */
        $team = $invitation->team;
        /** @var User $owner */
        $owner = $team->owner;
        $role = $invitation->role;

        return [
            'invitation' => [
                'id' => $invitation->id,
                'email' => $invitation->email,
            ],
            // The accept POST is `signed`; only this signed URL (derived from the
            // same email-proven link) can be used to accept, so an attacker can't
            // POST to a guessed invitation id.
            'acceptUrl' => URL::signedRoute('teams.invitation.accept', ['invitation' => $invitation->id]),
            'team' => [
                'id' => $team->id,
                'name' => $team->name,
            ],
            'inviter' => [
                'name' => $owner->name,
                'email' => $owner->email,
            ],
            'role' => [
                'code' => $role->code,
                'name' => $role->name,
            ],
            'userExists' => $invitation->existingAccount() instanceof User,
        ];
    }
}
