<?php

use Support\License\LicenseSignature;
use Tests\Support\License\LicenseKeys;

/**
 * A license key covers the domains it was activated for, whatever the case,
 * port or trailing dot of the host the install runs on, and never a
 * look-alike or the parent domain.
 */
it('matches a host whatever its case, port or trailing dot', function (string $host, bool $covered): void {
    $license = LicenseSignature::verify(LicenseKeys::activatedFor('chat.acme.com'), LicenseKeys::PUBLIC_KEY);

    expect($license?->coversHost($host))->toBe($covered);
})->with([
    ['chat.acme.com', true],
    ['CHAT.Acme.com', true],
    ['chat.acme.com:8443', true],
    ['chat.acme.com.', true],
    ['acme.com', false],
    ['evil-chat.acme.com', false],
    ['', false],
]);
