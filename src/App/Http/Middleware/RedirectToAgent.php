<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

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
