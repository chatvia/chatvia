<?php

declare(strict_types=1);

use GuzzleHttp\Client;

/**
 * Spectra watches Laravel's HTTP client by itself; the streamed replies go
 * out through a plain Guzzle client, which SpectraServiceProvider builds on
 * Spectra's handler stack, so a streamed reply is logged like any other
 * call. Who may open Spectra is in tests/Feature/App/Admin/StaffToolsAccessTest.php.
 */
it('sends the streamed replies through Spectra\'s handler', function (): void {
    expect(app(Client::class)->getConfig('handler'))->toBe(app('spectra.guzzle.handler'));
});
