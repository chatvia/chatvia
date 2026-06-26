<?php

use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

it('exposes the default locale as a URL segment (not hidden)', function (): void {
    expect(LaravelLocalization::hideDefaultLocaleInURL())->toBeFalse();
});

it('serves the English site under the explicit /en prefix', function (): void {
    $this->get('/en')->assertOk();
    $this->get('/en/pricing')->assertOk();
});

it('redirects an un-prefixed first visit to the default-locale URL /en', function (): void {
    // A real first visit hits "/" with no locale in the URL. The harness pins the
    // landing routes to ROUTING_LOCALE (en) at boot, so reboot with it cleared to
    // reproduce the un-prefixed registration a fresh visitor actually gets.
    putenv('ROUTING_LOCALE');
    unset($_ENV['ROUTING_LOCALE'], $_SERVER['ROUTING_LOCALE']);
    $this->refreshApplication();

    // Deep-link path preservation (/pricing → /en/pricing) is mcamara's own
    // behaviour and is verified end-to-end against the real server; the harness
    // re-registers routes only once per boot, so a second request here would
    // reuse the first request's resolved locale state.
    try {
        $this->get('/')->assertRedirect('/en');
    } finally {
        putenv('ROUTING_LOCALE=en');
        $_ENV['ROUTING_LOCALE'] = $_SERVER['ROUTING_LOCALE'] = 'en';
    }
});
