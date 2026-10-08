<?php

namespace App\Http\Middleware\Access;

use App\Identity\Workspace\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks `chatter` users from any dashboard route. Their session is valid for
 * `/chat` and stays valid; we just don't let them open the control panel.
 *
 * A deactivated account is signed out instead. The login paths already refuse
 * it, but a session that was open before the deactivation (or one opened by a
 * path that doesn't check) must not keep working.
 */
class EnsureCanAccessDashboard
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user && $user->isDeactivated()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->with('error', __('Your account has been deactivated.'));
        }

        if ($user && ! $user->canAccessDashboard()) {
            return redirect()->route('home');
        }

        return $next($request);
    }
}
