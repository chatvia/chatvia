<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ResolveAgentFromSubdomain
{
    public function handle(Request $request, Closure $next)
    {
        $subdomain = $request->subdomain();

        if ($subdomain) {
            $agent = \App\Support\Agent::resolveAgentFromSubdomain($request->subdomain());

            abort_if(boolean: ! $agent, code: 404);
        }

        $request->merge(['agent' => $subdomain]);

        return $next($request);
    }
}
