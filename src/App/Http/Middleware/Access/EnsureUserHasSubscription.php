<?php

namespace App\Http\Middleware\Access;

use App\Identity\Workspace\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Dashboard gate (`user.subscribed`): the workspace must have an active
 * subscription. A teammate is checked against the workspace that employs
 * them. Without one the user is sent to the plans page.
 *
 * Passes for guests (the `auth` middleware handles them), for admins, and on
 * an install with billing turned off.
 */
class EnsureUserHasSubscription
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        /** @var User|null $user */
        $user = $request->user();

        if (! $user || ! config('billing.enabled', true) || $user->isAdmin()) {
            return $next($request);
        }

        $billing = ($user->isWorkspace() ? $user : $user->parentWorkspace)?->billing;

        if (! $billing?->subscribed()) {
            return redirect()->route('billing.packages');
        }

        return $next($request);
    }
}
