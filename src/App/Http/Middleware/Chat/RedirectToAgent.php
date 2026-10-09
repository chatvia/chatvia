<?php

namespace App\Http\Middleware\Chat;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sends agent traffic to the hosted chat (`web` group). A request outside
 * `/chat` that arrives on an agent subdomain, or names an agent with
 * `?agent=`, is redirected to that agent's chat home, so the marketing site
 * and dashboard never render on an agent's address.
 */
class RedirectToAgent
{
    public function handle(Request $request, Closure $next): Response
    {
        $subdomain = $request->subdomain();
        $explicit = $request->query('agent');
        $path = $request->path();

        if (! str_starts_with($path, 'chat')) {
            if ($subdomain && $subdomain !== 'www') {
                return to_route('chat.home');
            }

            if (is_string($explicit) && $explicit !== '') {
                return to_route('chat.home', ['agent' => $explicit]);
            }
        }

        return $next($request);
    }
}
