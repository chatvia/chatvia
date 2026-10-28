<?php

declare(strict_types=1);

use Support\License\Entitlements;
use Support\License\FreeTier;

/**
 * Entitlements reads the flags that SettingsConfig works out from the saved
 * key; anything but true keeps the features locked and the free limits on.
 */
it('unlocks Enterprise only when the flag is true', function (mixed $flag, bool $enterprise): void {
    config(['chatvia.license.enterprise' => $flag]);

    expect(app(Entitlements::class)->enterprise())->toBe($enterprise);
})->with([
    'true' => [true, true],
    'false' => [false, false],
    'missing' => [null, false],
    'a truthy string' => ['1', false],
]);

it('applies the free-use limits without a key', function (): void {
    config(['chatvia.license.licensed' => false]);

    $entitlements = app(Entitlements::class);

    expect($entitlements->licensed())->toBeFalse()
        ->and($entitlements->workspaceLimit())->toBe(FreeTier::WORKSPACES)
        ->and($entitlements->api())->toBeFalse()
        ->and($entitlements->voice())->toBeFalse()
        ->and($entitlements->handoff())->toBeFalse()
        ->and($entitlements->capabilities())->toBeFalse()
        ->and($entitlements->customSplitting())->toBeFalse();
});

it('lifts every free-use limit with a key', function (): void {
    config(['chatvia.license.licensed' => true]);

    $entitlements = app(Entitlements::class);

    expect($entitlements->workspaceLimit())->toBeNull()
        ->and($entitlements->api())->toBeTrue()
        ->and($entitlements->voice())->toBeTrue()
        ->and($entitlements->handoff())->toBeTrue()
        ->and($entitlements->capabilities())->toBeTrue()
        ->and($entitlements->customSplitting())->toBeTrue();
});
