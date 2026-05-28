<?php

namespace App\Http\Middleware;

use App\Auth\Models\User;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks users who may not open the control panel — i.e. members of chat-only
 * teams. Runs after `auth`, so a user is always present. Such a user is logged
 * out and sent back to the login screen; their session only grants chat access.
 */
class EnsureCanAccessDashboard
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user && ! $user->canAccessDashboard()) {
            Auth::guard('web')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')->withErrors([
                'email' => 'This account does not have access to the dashboard.',
            ]);
        }

        return $next($request);
    }
}
