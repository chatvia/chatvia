<?php

namespace App\Http\Controllers\Team\Invitations;

use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Invitation;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Show the landing page for a team invitation. Accepting the invitation runs
 * through {@see AcceptTeamInvitationController}.
 */
class TeamInvitationController
{
    public function show(Request $request, Invitation $invitation): Response
    {
        if ($invitation->hasExpired()) {
            return Inertia::render('auth/InvitationExpired', [
                'message' => __('This invitation has expired.'),
            ]);
        }

        /** @var Team $team */
        $team = $invitation->team;
        /** @var User $owner */
        $owner = $team->owner;
        $role = $invitation->role;

        $existingUser = $invitation->existingAccount();

        return Inertia::render('auth/AcceptInvitation', [
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
            'userExists' => $existingUser instanceof User,
        ]);
    }
}
