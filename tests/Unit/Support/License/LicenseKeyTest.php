<?php

use Support\License\LicenseKey;
use Support\License\LicenseSignature;
use Tests\Support\License\LicenseKeys;

/**
 * A license key covers the domains it was activated for, whatever the case,
 * port or trailing dot of the host the install runs on, and never a
 * look-alike or the parent domain. A yearly key works through its signed
 * last day and not after; a key with none never ends.
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

it('works through the last day of a yearly key and not after, and never ends without one', function (): void {
    $this->travelTo('2027-11-15 23:59:00');
    $yearly = LicenseSignature::verify(LicenseKeys::enterpriseUntil('2027-11-15', 'chat.acme.com'), LicenseKeys::PUBLIC_KEY);
    $endless = LicenseSignature::verify(LicenseKeys::activatedFor('chat.acme.com'), LicenseKeys::PUBLIC_KEY);
    $onLastDay = $yearly?->isExpired();

    $this->travelTo('2027-11-16 00:00:01');

    expect($yearly?->expires)->toBe('2027-11-15')
        ->and($onLastDay)->toBeFalse()
        ->and($yearly?->isExpired())->toBeTrue()
        ->and($endless?->expires)->toBeNull()
        ->and($endless?->isExpired())->toBeFalse()
        ->and($endless?->toPayload())->not->toHaveKey('expires');
});

it('reads no terms with a last day that is not a date', function (): void {
    expect(LicenseKey::fromPayload([
        'id' => LicenseKeys::ID,
        'licensee' => 'Acme Ltd',
        'domains' => [],
        'features' => ['hide_credit'],
        'issued' => '2026-10-15',
        'expires' => 'never',
    ]))->toBeNull();
});
