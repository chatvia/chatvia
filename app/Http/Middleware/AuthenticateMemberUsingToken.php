<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class AuthenticateMemberUsingToken
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->filled('auth_token')) {
            $authToken = $request->input('auth_token');

            if ($cachedAuthToken = Cache::get($authToken)) {
                Auth::guard('member')->loginUsingId($cachedAuthToken['member_id']);

                Cache::forget($authToken);

                return redirect()->route('chat.home');
            }
        }

        return $next($request);
    }
}
