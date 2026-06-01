<?php

namespace App\Http\Middleware\Access;

use App\Auth\Models\User;
use App\Billing\Enums\CapabilityEnum;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;
use ValueError;

/**
 * Gates a route by a plan capability. Apply as `capability:topics`,
 * `capability:pulse`, etc. The argument must be a value on
 * {@see CapabilityEnum}; unknown values throw at request time so a typo
 * is loud, not silently false (the historical `hasCapability('foo')` bug).
 */
class EnsureUserHasCapability
{
    /**
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next, string $capability): Response
    {
        $case = CapabilityEnum::tryFrom($capability)
            ?? throw new ValueError("Unknown capability `{$capability}` in route middleware.");

        /** @var User|null $user */
        $user = $request->user();

        if (! $user) {
            abort(401);
        }

        if ($user->isAdmin()) {
            return $next($request);
        }

        if (! $user->hasCapability($case)) {
            abort(403, "Your plan doesn't include {$case->label()}.");
        }

        return $next($request);
    }
}
