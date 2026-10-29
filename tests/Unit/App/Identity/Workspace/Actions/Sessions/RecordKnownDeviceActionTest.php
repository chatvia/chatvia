<?php

use App\Identity\Workspace\Actions\Sessions\RecordKnownDeviceAction;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\UserKnownDevice;

/**
 * One upsert per sign-in: a new device is inserted, a known one only moves
 * on, and a row another sign-in wrote a moment earlier is updated instead of
 * breaking the unique index.
 */
it('inserts the first device, then reports a further new one as unfamiliar', function (): void {
    $user = User::factory()->create();

    $first = app(RecordKnownDeviceAction::class)->execute($user, '203.0.113.5', 'Firefox/130');
    $further = app(RecordKnownDeviceAction::class)->execute($user, '198.51.100.9', null);

    expect([$first->isNew, $first->hasOtherDevices, $first->isUnfamiliar()])->toBe([true, false, false])
        ->and([$further->isNew, $further->hasOtherDevices, $further->isUnfamiliar()])->toBe([true, true, true])
        ->and(UserKnownDevice::query()->where('user_id', $user->id)->where('ip', '203.0.113.5')->sole()->fingerprint_hash)
        ->toBe(hash('sha256', '203.0.113.5|Firefox/130'))
        ->and(UserKnownDevice::query()->where('user_id', $user->id)->where('ip', '198.51.100.9')->sole()->user_agent)->toBeNull();
});

it('moves a known device on without inserting it again, even a row another sign-in just wrote', function (): void {
    $user = User::factory()->create();
    $this->travelTo('2026-05-01 09:00:00');
    UserKnownDevice::query()->create([
        'user_id' => $user->id,
        'fingerprint_hash' => hash('sha256', '203.0.113.5|Firefox/130'),
        'ip' => '203.0.113.5',
        'user_agent' => 'Firefox/130',
        'last_seen_at' => now(),
    ]);
    $this->travelTo('2026-05-03 18:30:00');

    $sighting = app(RecordKnownDeviceAction::class)->execute($user, '203.0.113.5', 'Firefox/130');

    $device = UserKnownDevice::query()->where('user_id', $user->id)->sole();
    expect($sighting->isNew)->toBeFalse()
        ->and($device->last_seen_at->toDateTimeString())->toBe('2026-05-03 18:30:00')
        ->and($device->created_at?->toDateTimeString())->toBe('2026-05-01 09:00:00');
});
