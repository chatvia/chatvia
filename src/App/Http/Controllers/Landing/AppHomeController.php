<?php

namespace App\Http\Controllers\Landing;

use App\Identity\Workspace\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * `/` when the public marketing site is turned off
 * (`chatvia.brand.marketing_site`): there is no landing page, so visitors go
 * straight to the app — guests to the login form, workspace users to their
 * dashboard.
 */
class AppHomeController
{
    public function __invoke(Request $request): RedirectResponse
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user === null) {
            return redirect()->route('login');
        }

        // A chatter (end-user account) has no dashboard; sending it there would
        // bounce straight back here, so stop instead of looping.
        abort_unless($user->canAccessDashboard(), 403);

        return redirect()->route('dashboard');
    }
}
