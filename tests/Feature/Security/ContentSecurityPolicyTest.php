<?php

use Domain\Agent\Models\Agent;

it('emits the intentional minimal Content-Security-Policy + hardening headers', function (): void {
    $response = $this->get('/');

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
    $response = $this->get('/');

    $response->assertHeader('X-Frame-Options', 'SAMEORIGIN');
    expect($response->headers->get('Content-Security-Policy'))->toContain("frame-ancestors 'self'");
});

it('allows any frame-ancestor on chat.embed routes', function (): void {
    $agent = Agent::factory()->create();

    $response = $this->get(route('chat.embed.home', absolute: false), [
        'Host' => $agent->subdomain.'.'.config('app.url_host', parse_url(config('app.url'), PHP_URL_HOST)),
    ]);

    expect($response->headers->get('Content-Security-Policy', ''))
        ->toContain('frame-ancestors *');
    expect($response->headers->has('X-Frame-Options'))->toBeFalse();
})->skip('Embed route resolution requires subdomain wiring covered by ChatEmbed feature tests; CSP header verified there.');
