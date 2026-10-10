<?php

namespace App\Http\Middleware\Auth;

use App\Identity\Workspace\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hosted chat: signs out a user whose account was deactivated, on their very
 * next request. The login paths refuse a deactivated account, but they cannot
 * end a session that was already open, so without this a chatter deactivated
 * mid-session could keep chatting until the session expired.
 *
 * Runs in the `chat` group after the sign-in-link middlewares (so a link that
 * signs in a deactivated account is undone at once) and before
 * EnsureChatIsAccessible. The dashboard has the same rule in
 * EnsureCanAccessDashboard.
 */
class LogoutDeactivatedUser
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $user = Auth::guard()->user();

        if (! $user instanceof User || ! $user->isDeactivated()) {
            return $next($request);
        }

        Auth::guard()->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('chat.login.show')->with('error', __('Your account has been deactivated.'));
    }
}
