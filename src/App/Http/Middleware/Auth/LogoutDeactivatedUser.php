<?php

namespace App\Http\Middleware\Auth;

use App\Http\Chat\HostedChatViewer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hosted chat: signs out a chatter or staff account that was deactivated, on
 * their very next request. The login paths refuse a deactivated account, but they cannot
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
        $account = HostedChatViewer::account();

        if ($account === null || ! $account->isDeactivated()) {
            return $next($request);
        }

        HostedChatViewer::signOut();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return to_route('chat.login.show')->with('error', __('Your account has been deactivated.'));
    }
}
