<?php

namespace App\Http\Controllers\Team\Invitations;

use App\Http\Auth\TwoFactorAwareSignIn;
use App\Http\Requests\Team\AcceptInvitationRequest;
use Domain\Membership\Team\Actions\Invitations\AcceptTeamInvitationAction;
use Domain\Membership\Team\Models\Invitation;
use Illuminate\Http\RedirectResponse;

/**
 * Accept a pending team invitation: creates (or attaches) the invited user as a
 * team member, pivots them onto the invitation's team and signs them in. The
 * sign-in goes through {@see TwoFactorAwareSignIn}, so an existing account
 * with two-factor authentication still has to pass the challenge.
 */
class AcceptTeamInvitationController
{
    public function __invoke(
        AcceptInvitationRequest $request,
        Invitation $invitation,
        AcceptTeamInvitationAction $accept,
        TwoFactorAwareSignIn $signIn,
    ): RedirectResponse {
        $team = $invitation->team;

        $user = $accept->execute($request->toData($invitation));

        $user->switchTeam($team);

        return $signIn->respond($user, route('dashboard'))
            ->with('success', __('Welcome to :team!', ['team' => $team->name]));
    }
}
