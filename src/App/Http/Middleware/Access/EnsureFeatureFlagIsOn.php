<?php

namespace App\Http\Middleware\Access;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Guards the routes of an unfinished feature behind its install-wide flag in
 * `chatvia.features` (for example `->middleware(EnsureFeatureFlagIsOn::class.':youtube_sources')`).
 * While the flag is off these routes answer 404, as if they did not exist.
 */
class EnsureFeatureFlagIsOn
{
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        abort_unless((bool) config("chatvia.features.{$feature}", false), 404);

        return $next($request);
    }
}
