<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureChatIsAccessible
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $subdomain = $request->subdomain();
        $path = $request->path();

        if (str_starts_with($path, 'chat')) {
            $agent = \App\Support\Agent::resolveAgentFromSubdomain($subdomain);

            if (! $agent->is_public) {
                if ($request->route()->getName() !== 'chat.login') {
                    return to_route('chat.login');
                }
            }

            if ($request->route()->getName() === 'chat.login' && $agent->is_public) {
                return to_route('chat.home');
            }

            if (! $agent->is_active) {
                abort(403);
            }
        }

        return $next($request);
    }
}
