<?php

namespace App\Http\Controllers\Teams;

use App\Auth\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\Teams\AcceptInvitationRequest;
use App\Teams\Models\Invitation;
use App\Teams\Models\Team;
use Domain\Teams\Actions\AcceptTeamInvitationAction;
use Domain\Teams\Data\AcceptInvitationData;
use Domain\Teams\Exceptions\InviteRejectedException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

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

        if (! $owner->subscribed() && ! $owner->onTrial()) {
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

    public function accept(
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
