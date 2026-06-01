<?php

namespace App\Http\Controllers\Teams\Invitations;

use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\AcceptInvitationRequest;
use App\Teams\Models\Invitation;
use Domain\Teams\Actions\Invitations\AcceptTeamInvitationAction;
use Domain\Teams\Data\AcceptInvitationData;
use Domain\Teams\Exceptions\InviteRejectedException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Auth;

/**
 * Accept a pending team invitation: creates (or attaches) the invited user as a
 * team member, logs them in, and pivots them onto the invitation's team.
 */
class AcceptTeamInvitationController extends Controller
{
    public function __invoke(
        AcceptInvitationRequest $request,
        Invitation $invitation,
        AcceptTeamInvitationAction $accept,
    ): RedirectResponse {
        try {
            $user = $accept->execute(new AcceptInvitationData(
                invitation: $invitation,
                password: $request->input('password'),
                name: $request->input('name'),
            ));
        } catch (InviteRejectedException $e) {
            return back()->withErrors([$e->field => $e->getMessage()]);
        }

        Auth::login($user);
        $user->switchTeam($invitation->team);

        return redirect()->route('dashboard')
            ->with('success', "Welcome to {$invitation->team->name}!");
    }
}
