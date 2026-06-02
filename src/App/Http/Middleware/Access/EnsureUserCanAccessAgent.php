<?php

namespace App\Http\Middleware\Access;

use Closure;
use Domain\Agent\Models\Agent;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserCanAccessAgent
{
    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $agent = $request->route('agent');

        if ($agent instanceof Agent) {
            Gate::authorize('access-agent', $agent);
        }

        return $next($request);
    }
}
