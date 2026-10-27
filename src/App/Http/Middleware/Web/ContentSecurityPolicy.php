<?php

namespace App\Http\Middleware\Web;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Support\Http\SafeUrl;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers for every page in the `web` group (dashboard and the
 * hosted chat).
 *
 * Two headers go out in every mode, as they never break a page of the app:
 *  - framing: a page that is not the embeddable hosted chat may only be
 *    framed by the app itself (X-Frame-Options plus an enforced
 *    `frame-ancestors 'self'`), so no other site can lay it under a fake one
 *    (clickjacking).
 *  - HSTS, on a secure request in production. Plain-HTTP and local installs
 *    never get it.
 *
 * The rest of the policy follows `chatvia.security.csp` (Settings → Security):
 *  - `off`          only the two headers above.
 *  - `report-only`  (default) the policy goes out as
 *                   Content-Security-Policy-Report-Only: browsers only report
 *                   what it would block, so no page can break.
 *  - `enforce`      the policy goes out as Content-Security-Policy.
 *
 * The policy is deliberately small. It has no script-src / style-src /
 * connect-src / font-src / frame-src: the app needs 'unsafe-inline' (Alpine,
 * AOS, inline handlers), and adding a nonce would switch
 * 'unsafe-inline' off. So the Reverb websocket, Turnstile, the
 * tracking code and the asset CDN load without an allow-list (fonts are
 * self-hosted under /fonts, so 'self'). What is left are the directives that
 * don't fight asset loading.
 */
class ContentSecurityPolicy
{
    public const string MODE_OFF = 'off';

    public const string MODE_REPORT_ONLY = 'report-only';

    public const string MODE_ENFORCE = 'enforce';

    private const string FRAME_ANCESTORS_SELF = "frame-ancestors 'self'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // The hosted-chat surface (/chat/{agent}: every route is named
        // `chat.*`) is built to be embedded in third-party iframes. The auth
        // pages (login / magic-link) are the exception: a cross-origin-framable
        // login form is a clickjacking/UI-redress target, so those stay
        // same-origin only, like the dashboard.
        $embeddable = $request->routeIs('chat.*') && ! $request->routeIs('chat.login*', 'chat.magic-link*');

        self::applyFraming($response, $embeddable);
        self::applyHsts($request, $response);

        $mode = (string) config('chatvia.security.csp', self::MODE_REPORT_ONLY);

        if ($mode === self::MODE_OFF) {
            return $response;
        }

        // Anything that isn't exactly `enforce` (a typo included) only reports.
        $enforce = $mode === self::MODE_ENFORCE;

        $directives = [
            "object-src 'none'",
            "base-uri 'self'",
            // The app origin is listed next to 'self' because the marketing
            // pages can be served from another host (e.g. www) than APP_URL,
            // which is where their forms post.
            'form-action '.$this->sources(["'self'"], [config('app.url')]),
            $embeddable ? 'frame-ancestors *' : self::FRAME_ANCESTORS_SELF,
        ];

        // Defence-in-depth against prompt-injection image exfiltration on the
        // hosted-chat surface: even though the assistant renderer already
        // allow-lists <img> hosts, pin img-src so a model-emitted external image
        // is also blocked by the browser. Scoped to chat.* (where the legitimate
        // image origins are known) so it never fights the marketing/dashboard
        // asset pipeline. Does NOT require 'unsafe-inline'.
        if ($request->routeIs('chat.*')) {
            $directives[] = 'img-src '.$this->sources(["'self'", 'data:', 'blob:'], [
                // Chunk images are absolute URLs on the app domain.
                config('app.url'),
                // Built assets when they are served from a CDN host.
                config('app.asset_url'),
                // Profile photos, agent logo and avatar: the public bucket's
                // URL, or the local disk URL when no public bucket is set.
                config('filesystems.disks.assets.url'),
            ]);
        }

        $reportUri = Str::trim((string) config('chatvia.security.csp_report_uri', ''));

        if ($reportUri !== '') {
            $directives[] = "report-uri {$reportUri}";
        }

        // In enforce mode the full policy replaces the framing-only one; in
        // report-only mode it goes next to it, and only reports.
        $response->headers->set(
            $enforce ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only',
            Arr::join($directives, '; '),
        );

        // These two never block a page load, so they go out in both modes.
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        return $response;
    }

    /**
     * Same-origin framing only, enforced whatever the CSP mode: X-Frame-Options
     * for older browsers and `frame-ancestors 'self'`, which newer ones
     * prefer. An embeddable page gets neither, so any site can frame it. A
     * response that carries a policy of its own (a served SVG) keeps it. Also
     * used by the admin panel, which has no other CSP.
     */
    public static function applyFraming(Response $response, bool $embeddable = false): void
    {
        if ($embeddable) {
            $response->headers->remove('X-Frame-Options');

            return;
        }

        $response->headers->set('X-Frame-Options', 'SAMEORIGIN');

        if (! $response->headers->has('Content-Security-Policy')) {
            $response->headers->set('Content-Security-Policy', self::FRAME_ANCESTORS_SELF);
        }
    }

    /**
     * HSTS: browsers use HTTPS for the host and its subdomains for two years.
     * Only on a secure request in production, so a plain-HTTP install (a first
     * Docker run on localhost) or local development never gets it, as a
     * browser would then refuse that address over HTTP.
     */
    public static function applyHsts(Request $request, Response $response): void
    {
        if ($request->secure() && app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains');
        }
    }

    /**
     * Join fixed source keywords with the origins of configured URLs, skipping
     * empty or malformed values and duplicates.
     *
     * @param  list<string>  $keywords
     * @param  list<mixed>  $urls
     */
    private function sources(array $keywords, array $urls): string
    {
        $sources = $keywords;

        foreach ($urls as $url) {
            $origin = $this->originOf((string) $url);

            if ($origin !== null) {
                $sources[] = $origin;
            }
        }

        return Arr::join(array_values(array_unique($sources)), ' ');
    }

    /**
     * Reduce a configured URL to its `scheme://host[:port]` origin for use as a
     * CSP source, or null when the value is empty/malformed. A relative path is
     * same-origin ('self' covers it). A bare host (no scheme, as a CDN pull
     * zone is sometimes configured) is taken as https.
     */
    private function originOf(string $url): ?string
    {
        $url = Str::trim($url);

        if ($url === '' || (Str::startsWith($url, '/') && ! Str::startsWith($url, '//'))) {
            return null;
        }

        if (! Str::contains($url, '://')) {
            $url = 'https://'.Str::ltrim($url, '/');
        }

        $uri = SafeUrl::parse($url);

        if (blank($uri?->scheme()) || blank($uri->host())) {
            return null;
        }

        $port = $uri->port() !== null ? ":{$uri->port()}" : '';

        return "{$uri->scheme()}://{$uri->host()}{$port}";
    }
}
