<?php

declare(strict_types=1);

use Domain\Notification\Data\NotificationPreferencesData;

/**
 * A person's notification switches as stored: a category they never saved
 * takes its defaults, and with the bell off a category sends no desktop
 * alert or sound either.
 */
it('keeps saved switches and fills a category it never saved with its defaults', function (): void {
    $preferences = NotificationPreferencesData::fromStored([
        'leads' => ['bell' => true, 'desktop' => true, 'sound' => true],
    ]);

    expect($preferences->leads->desktop)->toBeTrue()
        ->and($preferences->leads->sound)->toBeTrue()
        ->and($preferences->handoffs->sound)->toBeTrue();
});

it('turns desktop alerts and sound off with the bell', function (): void {
    $preferences = NotificationPreferencesData::fromStored([
        'handoffs' => ['bell' => false, 'desktop' => true, 'sound' => true],
    ]);

    expect($preferences->handoffs->bell)->toBeFalse()
        ->and($preferences->handoffs->desktop)->toBeFalse()
        ->and($preferences->handoffs->sound)->toBeFalse();
});
