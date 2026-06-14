<?php

use Domain\Billing\Data\Resources\Package\BillingPackageCapabilitiesData as Capabilities;

/**
 * Pins the security tiering: the `security` suite (network access, retention,
 * audit viewer) and the standalone `siemWebhook` capability both belong to Pro
 * (the `pro` key) and Enterprise (= Pro + manual grants), and nothing below.
 */
it('grants the SIEM webhook capability to Pro and Enterprise only', function (): void {
    expect(Capabilities::pro()->siemWebhook)->toBeTrue()
        ->and(Capabilities::enterprise()->siemWebhook)->toBeTrue()
        ->and(Capabilities::standard()->siemWebhook)->toBeFalse()
        ->and(Capabilities::starter()->siemWebhook)->toBeFalse();
});

it('keeps the security suite on Pro and Enterprise only', function (): void {
    expect(Capabilities::pro()->security)->toBeTrue()
        ->and(Capabilities::enterprise()->security)->toBeTrue()
        ->and(Capabilities::standard()->security)->toBeFalse()
        ->and(Capabilities::starter()->security)->toBeFalse();
});

it('round-trips the SIEM capability through fromArray', function (): void {
    expect(Capabilities::fromArray(['siemWebhook' => true])->siemWebhook)->toBeTrue()
        ->and(Capabilities::fromArray([])->siemWebhook)->toBeFalse();
});
