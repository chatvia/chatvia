<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Identity\Workspace\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * `/` (route `home`). There is no public landing page: guests go to the login
 * form and signed-in workspace users to their dashboard.
 */
class HomeController
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
