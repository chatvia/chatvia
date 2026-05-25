<?php

namespace App\Http\Controllers;

use App\Auth\Models\User;
use App\Teams\Models\Invitation;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Routing\Redirector;

class InviteController extends Controller
{
    /**
     * Accept the given invite.
     */
    public function inviteAccept(Request $request, $invitationId): \Illuminate\Foundation\Application|Redirector|Application|RedirectResponse
    {
        // Get the invitation
        $invitation = Invitation::with(['team', 'role'])->findOrFail($invitationId);
        $team = $invitation->team;
        $role = $invitation->role;

        /** @var User $user */
        $user = $request->user();

        // Attach user to team with the role
        $team->members()->syncWithoutDetaching([
            $user->id => ['role_id' => $role->id],
        ]);

        // Delete the invitation
        $invitation->delete();

        return redirect('/')->with('status', __('Success! You have accepted the invitation to join the :team team.', ['team' => $team->name]));
    }
}
