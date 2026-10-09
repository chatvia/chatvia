<?php

namespace App\Http\Middleware\Access;

use App\Identity\Workspace\Models\User;
use Closure;
use Domain\Billing\Enums\Capability;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use ValueError;

/**
 * Gates a route by a plan capability. Apply as `capability:topics`,
 * `capability:pulse`, etc. The argument must be a value on
 * {@see Capability}; an unknown value throws at request time, so a typo
 * fails loudly instead of quietly denying everyone. Admins always pass.
 */
class EnsureUserHasCapability
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $case = Capability::tryFrom($capability)
            ?? throw new ValueError("Unknown capability `{$capability}` in route middleware.");

        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            abort(Response::HTTP_UNAUTHORIZED);
        }

        if ($user->isAdmin()) {
            return $next($request);
        }

        // Resolve against the user's current team (its plan), falling back to
        // the user's own plan when there's no team context (e.g. API tokens).
        $allowed = $user->currentTeam?->hasCapability($case) ?? $user->hasCapability($case);

        if (! $allowed) {
            abort(Response::HTTP_FORBIDDEN, __('Your plan does not include :feature.', ['feature' => $case->label()]));
        }

        return $next($request);
    }
}
