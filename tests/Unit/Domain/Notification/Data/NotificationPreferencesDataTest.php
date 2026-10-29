<?php

declare(strict_types=1);

use Domain\Notification\Data\NotificationPreferencesData;

/**
 * A person's notification switches as stored: with the bell off a category
 * sends no desktop alert or sound either.
 */
it('turns desktop alerts and sound off with the bell', function (): void {
    $preferences = NotificationPreferencesData::fromStored([
        'handoffs' => ['bell' => false, 'desktop' => true, 'sound' => true],
    ]);

    expect($preferences->handoffs->bell)->toBeFalse()
        ->and($preferences->handoffs->desktop)->toBeFalse()
        ->and($preferences->handoffs->sound)->toBeFalse();
});
