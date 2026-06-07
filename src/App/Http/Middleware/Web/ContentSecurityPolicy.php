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
        // `chat.*`) is built to be embedded in third-party iframes. The auth
        // pages (login / magic-link) are the exception: a cross-origin-framable
        // login form is a clickjacking/UI-redress target, so those stay
        // same-origin only. Everything else (dashboard + marketing on the apex)
        // is same-origin too.
        $embeddable = $request->routeIs('chat.*') && ! $request->routeIs('chat.login*', 'chat.magic-link*');

        if ($embeddable) {
            $cspDirectives[] = 'frame-ancestors *';
            $response->headers->remove('X-Frame-Options');
        } else {
            $cspDirectives[] = "frame-ancestors 'self'";
            $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
        }

        // Defence-in-depth against prompt-injection image exfiltration on the
        // hosted-chat surface: even though the assistant renderer already
        // allow-lists <img> hosts, pin img-src so a model-emitted external image
        // is also blocked by the browser. Scoped to chat.* (where the legitimate
        // image origins are known: self, inline data, the app domain that serves
        // chunk images, and the public storage/CDN host) so it never fights the
        // marketing/dashboard asset pipeline. Does NOT require 'unsafe-inline'.
        if ($request->routeIs('chat.*')) {
            $imgSources = ["'self'", 'data:', 'blob:'];

            foreach ([config('app.url'), config('filesystems.disks.public.url')] as $candidate) {
                $origin = $this->originOf((string) $candidate);
                if ($origin !== null) {
                    $imgSources[] = $origin;
                }
            }

            $cspDirectives[] = 'img-src '.implode(' ', array_values(array_unique($imgSources)));
        }

        $response->headers->set('Content-Security-Policy', implode('; ', $cspDirectives));

        // Additional security headers.
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // HSTS: force HTTPS for two years incl. subdomains. Only over a secure
        // connection in production so local/dev (http) is unaffected.
        if ($request->secure() && app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains; preload');
        }

        return $response;
    }

    /**
     * Reduce a configured URL to its `scheme://host[:port]` origin for use as a
     * CSP source, or null when the value is empty/malformed.
     */
    private function originOf(string $url): ?string
    {
        if ($url === '') {
            return null;
        }

        $parts = parse_url($url);

        if (! is_array($parts) || empty($parts['scheme']) || empty($parts['host'])) {
            return null;
        }

        $origin = $parts['scheme'].'://'.$parts['host'];

        if (isset($parts['port'])) {
            $origin .= ':'.$parts['port'];
        }

        return $origin;
    }
}
