<?php

use Domain\Agent\Models\Agent;

it('emits a Content-Security-Policy header on the landing page', function (): void {
    $response = $this->get('/');

    $response->assertHeader('Content-Security-Policy');
    $response->assertHeader('X-Content-Type-Options', 'nosniff');
    $response->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

    expect($response->headers->get('Content-Security-Policy'))
        ->toContain("default-src 'self'")
        ->toContain("frame-ancestors 'self'");
});

it('allows the inline scripts/styles and CDN libraries the pages actually use', function (): void {
    $csp = $this->get('/')->headers->get('Content-Security-Policy');

    $directives = explode('; ', $csp);
    [$scriptSrc] = array_values(preg_grep('/^script-src /', $directives));
    [$styleSrc] = array_values(preg_grep('/^style-src /', $directives));

    // Inline handlers (onclick), Alpine x-data, AOS init and the dark-mode IIFE
    // cannot carry a nonce, so 'unsafe-inline' must take effect (i.e. no nonce
    // alongside it, which would silently disable it).
    expect($scriptSrc)
        ->toContain("'unsafe-inline'")
        ->not->toContain('nonce-')
        ->toContain('https://cdnjs.cloudflare.com')  // AOS
        ->toContain('https://unpkg.com');            // Alpine

    expect($styleSrc)
        ->toContain("'unsafe-inline'")
        ->not->toContain('nonce-')
        ->toContain('https://cdnjs.cloudflare.com');

    expect($csp)
        ->toContain('connect-src ')   // Reverb / analytics / Vite HMR
        ->toContain('frame-src ');    // widget chat iframe
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
