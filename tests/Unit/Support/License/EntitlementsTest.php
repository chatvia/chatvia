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

it('applies the free-use limits without a key and lifts every one with a key', function (): void {
    config(['chatvia.license.licensed' => false]);
    $entitlements = app(Entitlements::class);
    $free = [$entitlements->licensed(), $entitlements->workspaceLimit(), $entitlements->api(), $entitlements->voice(), $entitlements->handoff(), $entitlements->capabilities(), $entitlements->advancedAnalytics(), $entitlements->customSplitting()];

    config(['chatvia.license.licensed' => true]);

    expect($free)->toBe([false, FreeTier::WORKSPACES, false, false, false, false, false, false])
        ->and($entitlements->workspaceLimit())->toBeNull()
        ->and($entitlements->api())->toBeTrue()
        ->and($entitlements->voice())->toBeTrue()
        ->and($entitlements->handoff())->toBeTrue()
        ->and($entitlements->capabilities())->toBeTrue()
        ->and($entitlements->advancedAnalytics())->toBeTrue()
        ->and($entitlements->customSplitting())->toBeTrue();
});
