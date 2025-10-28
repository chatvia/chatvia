<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;

class ForceWww
{
    public function handle(Request $request, Closure $next)
    {
        $host = $request->getHost();

        // If it's already a subdomain (contains a dot before chatvia.ai), do nothing
        if (substr_count($host, '.') >= 2 || ! app()->isProduction()) {
            return $next($request);
        }

        // If it's the bare domain (example.com), redirect to www
        if ($host === 'chatvia.ai') {
            return redirect()->to(
                $request->getScheme().'://www.chatvia.ai'.$request->getRequestUri(),
                301
            );
        }

        return $next($request);
    }
}
