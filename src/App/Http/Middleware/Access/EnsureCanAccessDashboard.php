<?php

namespace App\Http\Middleware\Access;

use App\Auth\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Blocks `chatter` users from any dashboard route. Their session is valid for
 * `/chat` and stays valid; we just don't let them open the control panel.
 */
class EnsureCanAccessDashboard
{
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if ($user && ! $user->canAccessDashboard()) {
            return redirect()->route('home');
        }

        return $next($request);
    }
}
