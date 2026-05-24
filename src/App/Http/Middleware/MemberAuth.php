<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MemberAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $subdomain = $request->subdomain();

        return $next($request);
    }
}
