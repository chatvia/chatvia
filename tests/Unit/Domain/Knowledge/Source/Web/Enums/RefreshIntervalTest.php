<?php

use Domain\Knowledge\Source\Web\Enums\RefreshInterval;

/**
 * Whether a website's refresh interval has passed since its last refresh: off
 * is never due, a website never refreshed is due at once.
 */
it('knows when each interval is due', function (RefreshInterval $interval, ?string $refreshedAt, bool $due): void {
    $now = now();

    expect($interval->isDue($refreshedAt === null ? null : $now->copy()->sub($refreshedAt), $now))->toBe($due);
})->with([
    'off is never due' => [RefreshInterval::Off, null, false],
    'never refreshed' => [RefreshInterval::Weekly, null, true],
    'daily, a day ago' => [RefreshInterval::Daily, '1 day', true],
    'weekly, six days ago' => [RefreshInterval::Weekly, '6 days', false],
]);
