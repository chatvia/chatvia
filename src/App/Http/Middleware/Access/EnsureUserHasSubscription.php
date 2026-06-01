<?php

namespace App\Http\Middleware\Access;

use App\Auth\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasSubscription
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user();

        if (! $user) {
            return $next($request);
        }

        if ($user->isAdmin()) {
            return $next($request);
        }

        $billing = ($user->isWorkspace() ? $user : $user->workspace)?->billing;

        if (! $billing?->subscribed()) {
            return redirect()->route('billing.packages');
        }

        return $next($request);
    }
}
