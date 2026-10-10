<?php

namespace App\Http\Middleware\Web;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Security headers for every page in the `web` group (marketing site,
 * dashboard and the hosted chat).
 *
 * The mode comes from `chatvia.security.csp` (env CSP_MODE):
 *  - `off`          no headers at all.
 *  - `report-only`  (default) the policy goes out as
 *                   Content-Security-Policy-Report-Only, and the headers that
 *                   block (X-Frame-Options, HSTS) are left out. Browsers only
 *                   report what the policy would block, so no page can break.
 *  - `enforce`      Content-Security-Policy, X-Frame-Options, and HSTS in
 *                   production over HTTPS.
 *
 * The policy is deliberately small. It has no script-src / style-src /
 * connect-src / font-src / frame-src: the app needs 'unsafe-inline' (Alpine,
 * AOS, inline handlers), and adding a nonce would switch
 * 'unsafe-inline' off. So the Reverb websocket, Turnstile,
 * Plausible and the asset CDN load without an allow-list (fonts are
 * self-hosted under /fonts, so 'self'). What is left are the directives that
 * don't fight asset loading.
 */
class ContentSecurityPolicy
{
    public const MODE_OFF = 'off';

    public const MODE_REPORT_ONLY = 'report-only';

    public const MODE_ENFORCE = 'enforce';

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

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
        ];

        // The hosted-chat surface (the agent subdomains — every route is named
        // `chat.*`) is built to be embedded in third-party iframes. The auth
        // pages (login / magic-link) are the exception: a cross-origin-framable
        // login form is a clickjacking/UI-redress target, so those stay
        // same-origin only. Everything else (dashboard + marketing on the apex)
        // is same-origin too.
        $embeddable = $request->routeIs('chat.*') && ! $request->routeIs('chat.login*', 'chat.magic-link*');

        $directives[] = $embeddable ? 'frame-ancestors *' : "frame-ancestors 'self'";

        if ($enforce) {
            if ($embeddable) {
                $response->headers->remove('X-Frame-Options');
            } else {
                $response->headers->set('X-Frame-Options', 'SAMEORIGIN');
            }
        }

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
                // Chat attachments (public bucket).
                config('filesystems.disks.public.url'),
                // Profile photos, agent logo and avatar: the CDN pull zone, or
                // the local public disk URL when no CDN is configured.
                config('filesystems.disks.assets.pull_zone'),
                config('filesystems.disks.assets.url'),
            ]);
        }

        $reportUri = trim((string) config('chatvia.security.csp_report_uri', ''));

        if ($reportUri !== '') {
            $directives[] = 'report-uri '.$reportUri;
        }

        $response->headers->set(
            $enforce ? 'Content-Security-Policy' : 'Content-Security-Policy-Report-Only',
            implode('; ', $directives),
        );

        // These two never block a page load, so they go out in both modes.
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // HSTS: force HTTPS for two years incl. subdomains. Only when enforcing,
        // and only over a secure connection in production so local/dev (http)
        // is unaffected.
        if ($enforce && $request->secure() && app()->isProduction()) {
            $response->headers->set('Strict-Transport-Security', 'max-age=63072000; includeSubDomains; preload');
        }

        return $response;
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

        return implode(' ', array_values(array_unique($sources)));
    }

    /**
     * Reduce a configured URL to its `scheme://host[:port]` origin for use as a
     * CSP source, or null when the value is empty/malformed. A relative path is
     * same-origin ('self' covers it). A bare host (no scheme, as a CDN pull
     * zone is sometimes configured) is taken as https.
     */
    private function originOf(string $url): ?string
    {
        $url = trim($url);

        if ($url === '' || (str_starts_with($url, '/') && ! str_starts_with($url, '//'))) {
            return null;
        }

        if (! str_contains($url, '://')) {
            $url = 'https://'.ltrim($url, '/');
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
