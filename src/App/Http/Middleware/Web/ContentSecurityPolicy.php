<?php

namespace App\Http\Middleware\Web;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ContentSecurityPolicy
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // We deliberately do NOT ship script-src/style-src/font-src/connect-src
        // policies. The app needs 'unsafe-inline' + 'unsafe-eval' anyway (Alpine,
        // AOS, Ziggy @routes, inline handlers), which neuters CSP's main XSS
        // benefit, while the allowlist broke on every new CDN/font/dev-server
        // origin (KaTeX + primeicons fonts, Vite HMR, ...). What's left here are
        // the cheap, high-value headers that never fight asset loading.
        $cspDirectives = [
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        // The hosted-chat surface (the agent subdomains — every route is named
        // `chat.*`) is built to be embedded in third-party iframes, including the
        // login page a private agent's `/chat/embed` redirects to. Anything else
        // (dashboard + marketing on the apex) stays same-origin only.
        if ($request->routeIs('chat.*')) {
            $cspDirectives[] = 'frame-ancestors *';
            $response->headers->remove('X-Frame-Options');
        } else {
            $cspDirectives[] = "frame-ancestors 'self'";
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        $response->headers->set('Content-Security-Policy', implode('; ', $cspDirectives));

        // Additional security headers.
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}
