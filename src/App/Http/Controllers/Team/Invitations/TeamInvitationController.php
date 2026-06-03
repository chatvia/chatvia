<?php

namespace App\Http\Controllers\Team\Invitations;

use App\Http\Controllers\Controller;
use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Invitation;
use Domain\Membership\Team\Models\Team;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Show the landing page for a team invitation. Accepting the invitation runs
 * through {@see AcceptTeamInvitationController}.
 */
class TeamInvitationController extends Controller
{
    public function show(Request $request, Invitation $invitation): Response
    {
        if ($invitation->created_at->addDays(7)->isPast()) {
            return Inertia::render('auth/InvitationExpired', [
                'message' => 'This invitation has expired.',
            ]);
        }

        /** @var Team $team */
        $team = $invitation->team;
        /** @var User $owner */
        $owner = $team->owner;
        $role = $invitation->role;

        if (! $owner->hasActiveSubscription()) {
            return Inertia::render('auth/InvitationInvalid', [
                'message' => 'The team owner does not have an active subscription. Please contact them to upgrade their plan.',
                'team' => ['name' => $team->name],
            ]);
        }

        $existingUser = User::where('email', $invitation->email)->first();

        return Inertia::render('auth/AcceptInvitation', [
            'invitation' => [
                'id' => $invitation->id,
                'email' => $invitation->email,
            ],
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
            'userExists' => $existingUser !== null,
        ]);
    }
}
