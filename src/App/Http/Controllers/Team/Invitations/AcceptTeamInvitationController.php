<?php

namespace App\Http\Controllers\Team\Invitations;

use App\Http\Requests\Team\AcceptInvitationRequest;
use Domain\Membership\Team\Actions\Invitations\AcceptTeamInvitationAction;
use Domain\Membership\Team\Data\AcceptInvitationData;
use Domain\Membership\Team\Models\Invitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Accept a pending team invitation: creates (or attaches) the invited user as a
 * team member, logs them in, and pivots them onto the invitation's team.
 */
class AcceptTeamInvitationController
{
    public function __invoke(
        AcceptInvitationRequest $request,
        Invitation $invitation,
        AcceptTeamInvitationAction $accept,
    ): RedirectResponse {
        $user = $accept->execute(new AcceptInvitationData(
            invitation: $invitation,
            password: $request->input('password'),
            name: $request->input('name'),
        ));

        Auth::login($user);

        $user->switchTeam($invitation->team);

        return redirect()->route('dashboard')
            ->with('success', "Welcome to {$invitation->team->name}!");
    }
}
