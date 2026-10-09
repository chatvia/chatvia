<?php

namespace App\Http\Middleware\Auth;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Hosted chat counterpart of Laravel's `auth` middleware (alias
 * `auth.chatter`): the visitor must be signed in on the `web` guard. A guest
 * gets a 401 for a JSON call and is sent to the chat home otherwise, where the
 * chat's own access rules decide whether to show the login page.
 */
class AuthenticateChatter
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::guard('web')->check()) {
            return $next($request);
        }

        if ($request->expectsJson()) {
            return response()->json(['message' => 'Unauthenticated.'], Response::HTTP_UNAUTHORIZED);
        }

        return redirect()->route('chat.home');
    }
}
