<?php

use App\Http\Middleware\Web\ContentSecurityPolicy;
use Illuminate\Contracts\Routing\ResponseFactory;
use Illuminate\Support\Facades\Route;

// These tests pin the ContentSecurityPolicy middleware's header contract by
// mounting it on probe routes. It is also registered globally in the `web`
// group (bootstrap/app.php); CSP_MODE picks off / report-only / enforce.
beforeEach(function (): void {
    Route::middleware(ContentSecurityPolicy::class)
        ->get('/_csp-probe', fn (): ResponseFactory|\Illuminate\Http\Response => response('ok'))
        ->name('csp.probe');

    // Named `chat.*` so the middleware treats it as the embeddable
    // hosted-chat surface (no subdomain wiring needed for header checks).
    Route::middleware(ContentSecurityPolicy::class)
        ->get('/_csp-chat-probe', fn (): ResponseFactory|\Illuminate\Http\Response => response('ok'))
        ->name('chat.csp-probe');
});

describe('enforce mode', function (): void {
    beforeEach(function (): void {
        config(['chatvia.security.csp' => ContentSecurityPolicy::MODE_ENFORCE]);
    });

    it('emits the intentional minimal Content-Security-Policy + hardening headers', function (): void {
        $response = $this->get('/_csp-probe');

        $response->assertHeader('Content-Security-Policy');
        $response->assertHeaderMissing('Content-Security-Policy-Report-Only');
        $response->assertHeader('X-Content-Type-Options', 'nosniff');
        $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        // The app deliberately ships a minimal CSP (no script-src/style-src
        // allowlist): it requires 'unsafe-inline' (Alpine, AOS, Ziggy @routes,
        // inline handlers) which a nonce would silently disable, and the
        // allowlist kept breaking on new CDN/font/Turnstile/Stripe/Vite origins.
        // What remains are the high-value directives that never fight asset
        // loading.
        expect($response->headers->get('Content-Security-Policy'))
            ->toContain("object-src 'none'")
            ->toContain("base-uri 'self'")
            ->toContain("form-action 'self'")
            ->toContain("frame-ancestors 'self'")
            ->not->toContain('script-src')
            ->not->toContain('connect-src');
    });

    it('lets the trial sign-up form redirect to Stripe Checkout', function (): void {
        // The form POSTs to us and we 302 to Stripe; browsers check that
        // redirect against form-action.
        expect($this->get('/_csp-probe')->headers->get('Content-Security-Policy'))
            ->toContain('https://checkout.stripe.com');
    });

    it('frames the dashboard same-origin only (clickjacking)', function (): void {
        $response = $this->get('/_csp-probe');

        $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
        expect($response->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'self'");
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
            // A relative URL is same-origin, already covered by 'self'.
            'filesystems.disks.public.url' => '/storage',
        ]);

        $imgSrc = collect(explode('; ', (string) $this->get('/_csp-chat-probe')->headers->get('Content-Security-Policy')))
            ->first(fn (string $directive): bool => str_starts_with($directive, 'img-src'));

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

        $imgSrc = collect(explode('; ', (string) $this->get('/_csp-chat-probe')->headers->get('Content-Security-Policy')))
            ->first(fn (string $directive): bool => str_starts_with($directive, 'img-src'));

        expect($imgSrc)->toContain('https://files.example.org');
    });

    it('adds the report-uri when one is configured', function (): void {
        config(['chatvia.security.csp_report_uri' => 'https://reports.example.net/csp']);

        expect($this->get('/_csp-probe')->headers->get('Content-Security-Policy'))
            ->toContain('report-uri https://reports.example.net/csp');
    });
});

describe('report-only mode', function (): void {
    it('is the default', function (): void {
        expect(config('chatvia.security.csp'))->toBe(ContentSecurityPolicy::MODE_REPORT_ONLY);
    });

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
