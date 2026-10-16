<?php

use Domain\Notification\Data\NotificationPreferencesData;
use Domain\Notification\Enums\NotificationCategory;

it('rings the bell for everything and alerts the desktop for handoffs and security by default', function (): void {
    $defaults = NotificationPreferencesData::defaults();

    foreach (NotificationCategory::cases() as $category) {
        expect($defaults->for($category)->bell)->toBeTrue();
    }

    expect($defaults->handoffs->desktop)->toBeTrue()
        ->and($defaults->security->desktop)->toBeTrue()
        ->and($defaults->leads->desktop)->toBeFalse()
        ->and($defaults->handoffs->sound)->toBeTrue()
        ->and($defaults->security->sound)->toBeFalse();
});

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
