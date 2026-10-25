<?php

declare(strict_types=1);

namespace App\Http\Middleware\Web;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The always-on headers of {@see ContentSecurityPolicy} for pages outside the
 * `web` group, namely the admin panel: same-origin framing only (no other
 * site may lay the admin panel under a fake page) and HSTS on a secure
 * request in production.
 */
final class DenyCrossOriginFraming
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        ContentSecurityPolicy::applyFraming($response);
        ContentSecurityPolicy::applyHsts($request, $response);

        return $response;
    }
}
