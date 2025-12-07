<?php

namespace App\Http\Controllers\Teams;

use App\Http\Controllers\Controller;
use App\Models\Invitation;
use App\Models\Team;
use App\Models\User;
use App\Services\Teams\TeamService;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;

class TeamInvitationController extends Controller
{
    public function __construct(
        private TeamService $teamService
    ) {}

    /**
     * Show the invitation acceptance page.
     */
    public function show(Request $request, Invitation $invitation)
    {
        if (! $invitation || $invitation->created_at->addDays(7)->isPast()) {
            return Inertia::render('auth/InvitationExpired', [
                'message' => 'This invitation has expired.',
            ]);
        }

        $team = $invitation->team;
        $owner = $team->owner;
        $role = $invitation->role;

        // Check if the team owner has an active subscription
        if (! $owner->subscribed() && ! $owner->onTrial()) {
            return Inertia::render('auth/InvitationInvalid', [
                'message' => 'The team owner does not have an active subscription. Please contact them to upgrade their plan.',
                'team' => [
                    'name' => $team->name,
                ],
            ]);
        }

        // Check if the user already exists
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

    /**
     * Accept the invitation and create/login the user.
     */
    public function accept(Request $request, Invitation $invitation)
    {
        // Check if invitation is still valid
        if (! $invitation || $invitation->created_at->addDays(7)->isPast()) {
            return back()->withErrors(['invitation' => 'This invitation has expired.']);
        }

        $team = $invitation->team;
        $owner = $team->owner;

        // Check if the team owner has an active subscription
        if (! $owner->subscribed() && ! $owner->onTrial()) {
            return back()->withErrors(['invitation' => 'The team owner does not have an active subscription.']);
        }

        // Check if the user already exists
        $existingUser = User::where('email', $invitation->email)->first();

        if ($existingUser) {
            // User exists - validate password and log them in
            $request->validate([
                'password' => ['required', 'string'],
            ]);

            if (! Hash::check($request->password, $existingUser->password)) {
                return back()->withErrors(['password' => 'The provided password is incorrect.']);
            }

            $user = $existingUser;
        } else {
            // User doesn't exist - create new account
            $request->validate([
                'name' => ['required', 'string', 'max:255'],
                'password' => ['required', 'confirmed', Password::defaults()],
                'policy' => ['required', 'accepted'],
                'terms' => ['required', 'accepted'],
            ]);

            // Create the user without triggering the observer (we'll handle team assignment manually)
            $user = User::create([
                'name' => $request->name,
                'email' => $invitation->email,
                'password' => Hash::make($request->password),
                'email_verified_at' => now(), // Auto-verify since they received the invitation email
                'managed_by_user_id' => $owner->id, // User is managed by the team owner
                'type' => 'staff', // Staff user belongs to a company
            ]);

            event(new Registered($user));
        }

        // Add user to the team with the invited role
        $team->users()->attach($user->id, [
            'role_id' => $invitation->role_id,
            'is_owner' => false,
        ]);

        // Delete the invitation
        $invitation->delete();

        // Log the user in
        Auth::login($user);

        // Set the team they were invited to as the current team
        $user->switchTeam($team);

        return redirect()->route('dashboard')
            ->with('success', "Welcome to {$team->name}!");
    }
}
