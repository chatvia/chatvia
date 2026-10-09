<?php

namespace App\Http\Middleware\Auth;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hosted chat counterpart of Laravel's `guest` middleware (alias
 * `guest.chatter`): a visitor who is already signed in is sent to the chat
 * home instead of seeing a guest-only page such as the chat login.
 *
 * Checks the `web` guard unless other guards are passed as arguments.
 */
class RedirectIfChatterAuthenticated
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string ...$guards): Response
    {
        foreach ($guards === [] ? ['web'] : $guards as $guard) {
            if (Auth::guard($guard)->check()) {
                return redirect()->route('chat.home');
            }
        }

        return $next($request);
    }
}
