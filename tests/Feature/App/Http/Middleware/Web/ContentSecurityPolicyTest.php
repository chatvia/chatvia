<?php

use App\Http\Middleware\Web\ContentSecurityPolicy;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

/**
 * The headers the ContentSecurityPolicy middleware sends in each mode the
 * admin picks (`chatvia.security.csp`: off, report-only, enforce), checked on
 * probe routes that mount it, plus one real page to show it runs in the `web`
 * group. The hosted chat may be framed anywhere; the dashboard only by
 * itself.
 */
beforeEach(function (): void {
    Route::middleware(ContentSecurityPolicy::class)
        ->get('/_csp-probe', fn (): ResponseFactory|Response => response('ok'))
        ->name('csp.probe');

    // Named `chat.*` so the middleware treats it as the embeddable
    // hosted-chat surface (no subdomain wiring needed for header checks).
    Route::middleware(ContentSecurityPolicy::class)
        ->get('/_csp-chat-probe', fn (): ResponseFactory|Response => response('ok'))
        ->name('chat.csp-probe');
});

describe('enforce mode', function (): void {
    beforeEach(function (): void {
        config(['chatvia.security.csp' => ContentSecurityPolicy::MODE_ENFORCE]);
    });

    it('sends the minimal policy and the hardening headers, and frames the dashboard same-origin only', function (): void {
        $response = $this->get('/_csp-probe');

        $response->assertHeader('Content-Security-Policy');
        $response->assertHeaderMissing('Content-Security-Policy-Report-Only');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');

        // The app deliberately ships a minimal CSP (no script-src/style-src
        // allowlist): the pages need 'unsafe-inline', which a nonce would
        // silently disable, and an allowlist breaks whenever a new CDN, font
        // or Vite origin is added. What remains are the high-value directives
        // that never fight asset loading.
        expect($response->headers->get('Content-Security-Policy'))
            ->toContain("object-src 'none'")
            ->toContain("base-uri 'self'")
            ->toContain("form-action 'self'")
            ->toContain("frame-ancestors 'self'")
            ->not->toContain('script-src')
            ->not->toContain('connect-src');
    });

    it('allows any frame-ancestor on the embeddable chat surface', function (): void {
        $response = $this->get('/_csp-chat-probe');

        expect($response->headers->get('Content-Security-Policy', ''))
            ->toContain('frame-ancestors *')
            // Prompt-injection image-exfiltration defence: img-src is pinned on
            // the chat surface only.
            ->toContain('img-src');
        expect($response->headers->has('X-Frame-Options'))->toBeFalse();
    });

    it('lets the chat load images from the CDN pull zone and the asset host, but no third-party icon CDN', function (): void {
        config([
            'filesystems.disks.assets.pull_zone' => 'https://cdn-zone.example.net',
            'app.asset_url' => 'https://assets.example.net/build',
        ]);

        $imgSrc = Str::of((string) $this->get('/_csp-chat-probe')->headers->get('Content-Security-Policy'))->explode('; ')
            ->first(fn (string $directive): bool => Str::startsWith($directive, 'img-src'));

        expect($imgSrc)
            ->toContain('https://cdn-zone.example.net')
            ->toContain('https://assets.example.net')
            ->not->toContain('simpleicons')
            ->not->toContain('https://storage');
    });

    it('lets the chat load images from the local assets disk when no CDN is configured', function (): void {
        config([
            'filesystems.disks.assets' => [
                'driver' => 'local',
                'root' => storage_path('app/public'),
                'url' => 'https://files.example.org/storage',
                'visibility' => 'public',
            ],
        ]);

        $imgSrc = Str::of((string) $this->get('/_csp-chat-probe')->headers->get('Content-Security-Policy'))->explode('; ')
            ->first(fn (string $directive): bool => Str::startsWith($directive, 'img-src'));

        expect($imgSrc)->toContain('https://files.example.org');
    });

    it('adds the report-uri when one is configured', function (): void {
        config(['chatvia.security.csp_report_uri' => 'https://reports.example.net/csp']);

        expect($this->get('/_csp-probe')->headers->get('Content-Security-Policy'))
            ->toContain('report-uri https://reports.example.net/csp');
    });
});

describe('report-only mode', function (): void {
    it('only reports, and sends no header that could block a page', function (): void {
        config(['chatvia.security.csp' => ContentSecurityPolicy::MODE_REPORT_ONLY]);

        $response = $this->get('/_csp-probe');

        $response->assertHeader('Content-Security-Policy-Report-Only');
        $response->assertHeaderMissing('Content-Security-Policy');
        $response->assertHeaderMissing('X-Frame-Options');
        $response->assertHeaderMissing('Strict-Transport-Security');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');

        expect($response->headers->get('Content-Security-Policy-Report-Only'))
            ->toContain("object-src 'none'")
            ->toContain("frame-ancestors 'self'");
    });

    it('treats an unknown mode as report-only rather than enforcing it', function (): void {
        config(['chatvia.security.csp' => 'enforced-typo']);

        $response = $this->get('/_csp-probe');

        $response->assertHeader('Content-Security-Policy-Report-Only');
        $response->assertHeaderMissing('Content-Security-Policy');
    });
});

it('sends no security headers when switched off', function (): void {
    config(['chatvia.security.csp' => ContentSecurityPolicy::MODE_OFF]);

    $response = $this->get('/_csp-probe');

    $response->assertHeaderMissing('Content-Security-Policy');
    $response->assertHeaderMissing('Content-Security-Policy-Report-Only');
    $response->assertHeaderMissing('X-Content-Type-Options');
});

it('is registered on real web pages', function (): void {
    config(['chatvia.security.csp' => ContentSecurityPolicy::MODE_REPORT_ONLY]);

    $this->get(route('login'))
        ->assertOk()
        ->assertHeader('Content-Security-Policy-Report-Only');
});
