<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Vite;
use Symfony\Component\HttpFoundation\Response;

class ContentSecurityPolicy
{
    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Generate CSP nonce for inline scripts and styles
        Vite::useCspNonce();
        $nonce = Vite::cspNonce();

        // Determine if this is an embed route
        $isEmbedRoute = $request->routeIs('chat.embed.*');

        // Base CSP directives
        $cspDirectives = [
            "default-src 'self'",
            "script-src 'self' 'nonce-{$nonce}' 'unsafe-eval' https://www.googletagmanager.com https://www.google-analytics.com",
            "style-src 'self' 'nonce-{$nonce}' 'unsafe-inline' https://fonts.googleapis.com",
            "img-src 'self' data: https: blob:",
            "font-src 'self' https://fonts.gstatic.com data:",
            "media-src 'self' data: blob:",
            "object-src 'none'",
            "base-uri 'self'",
            "form-action 'self'",
        ];

        // Frame directives based on route
        if ($isEmbedRoute) {
            // Allow embedding in any iframe for embed routes
            $cspDirectives[] = 'frame-ancestors *';
            $response->headers->remove('X-Frame-Options');
        } else {
            // Restrict framing for non-embed routes
            $cspDirectives[] = "frame-ancestors 'self'";
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        // Set CSP header
        $response->headers->set('Content-Security-Policy', implode('; ', $cspDirectives));

        // Additional security headers
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-XSS-Protection', '1; mode=block');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }
}
