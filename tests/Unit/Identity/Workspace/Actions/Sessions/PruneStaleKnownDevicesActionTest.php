<?php

use App\Identity\Workspace\Actions\Sessions\PruneStaleKnownDevicesAction;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Models\UserKnownDevice;
use Illuminate\Support\Str;

/**
 * Sign-in devices nobody used for 180 days are forgotten, in batches; a device
 * seen within that time stays.
 */
function pskdDevice(User $user, int $daysAgo): UserKnownDevice
{
    return UserKnownDevice::query()->create([
        'user_id' => $user->id,
        'fingerprint_hash' => hash('sha256', Str::random()),
        'ip' => '203.0.113.9',
        'user_agent' => 'Test browser',
        'last_seen_at' => now()->subDays($daysAgo),
    ]);
}

it('deletes devices not seen for 180 days and keeps the rest', function (): void {
    $user = User::factory()->create();
    $stale = [pskdDevice($user, 181)->id, pskdDevice($user, 400)->id, pskdDevice($user, 365)->id];
    $kept = [pskdDevice($user, 179)->id, pskdDevice($user, 0)->id];

    $deleted = app(PruneStaleKnownDevicesAction::class)->execute(batchSize: 2);

    expect($deleted)->toBe(3)
        ->and(UserKnownDevice::query()->whereKey($stale)->exists())->toBeFalse()
        ->and(UserKnownDevice::query()->whereKey($kept)->count())->toBe(2);
});
