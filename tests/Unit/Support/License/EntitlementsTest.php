<?php

declare(strict_types=1);

use Support\License\Entitlements;

/**
 * Entitlements reads the Enterprise flag that SettingsConfig works out from
 * the saved key; anything but true keeps the features locked.
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
