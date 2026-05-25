<?php

namespace App\Http\Middleware;

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

        $userSubscribed = $user->isCompany() ?
            $user->subscribed() :
            $user->manager->subscribed();

        if (! $userSubscribed) {
            return redirect()->route('billing.packages');
        }

        return $next($request);
    }
}
