<?php

use App\Http\Middleware\Web\ContentSecurityPolicy;
use Illuminate\Support\Facades\Route;

// These tests pin the ContentSecurityPolicy middleware's exact header
// contract by mounting it on probe routes. NOTE: the global registration in
// bootstrap/app.php (web + chat groups) is currently commented out — it was
// disabled inside the unrelated Telegram-channel commit and live responses
// ship NO CSP headers until it is re-enabled there.
beforeEach(function (): void {
    Route::middleware(ContentSecurityPolicy::class)
        ->get('/_csp-probe', fn (): \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response => response('ok'))
        ->name('csp.probe');

    // Named `chat.*` so the middleware treats it as the embeddable
    // hosted-chat surface (no subdomain wiring needed for header checks).
    Route::middleware(ContentSecurityPolicy::class)
        ->get('/_csp-chat-probe', fn (): \Illuminate\Contracts\Routing\ResponseFactory|\Illuminate\Http\Response => response('ok'))
        ->name('chat.csp-probe');
});

it('emits the intentional minimal Content-Security-Policy + hardening headers', function (): void {
    $response = $this->get('/_csp-probe');

    $response->assertHeader('Content-Security-Policy');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    // The app deliberately ships a minimal CSP (no script-src/style-src
    // allowlist): it requires 'unsafe-inline' (Alpine, AOS, Ziggy @routes,
    // inline handlers) which a nonce would silently disable, and the allowlist
    // kept breaking on new CDN/font/Turnstile/Stripe/Vite origins. What remains
    // are the high-value directives that never fight asset loading.
    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("object-src 'none'")
        ->toContain("base-uri 'self'")
        ->toContain("form-action 'self'")
        ->toContain("frame-ancestors 'self'");
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
