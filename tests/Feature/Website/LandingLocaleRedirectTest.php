<?php

use Mcamara\LaravelLocalization\Facades\LaravelLocalization;

it('redirects the un-prefixed root to the default-locale URL /en', function (): void {
    $this->get('/')->assertRedirect('/en');
});

it('redirects an un-prefixed marketing page to its /en URL', function (): void {
    $this->get('/pricing')->assertRedirect('/en/pricing');
});

it('exposes the default locale as a URL segment (not hidden)', function (): void {
    expect(LaravelLocalization::hideDefaultLocaleInURL())->toBeFalse();
});
