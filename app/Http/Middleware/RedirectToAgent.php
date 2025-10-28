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
        $path = $request->path();

        if ($subdomain && $subdomain !== 'www' && ! str_starts_with($path, 'chat')) {
            return to_route('chat.home');
        }

        return $next($request);
    }
}
