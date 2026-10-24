<?php

use Domain\Notification\Actions\PruneNotificationsAction;
use Domain\Notification\Models\UserNotification;

/**
 * The nightly prune deletes notifications with no activity in the window,
 * read or not, in batches.
 */
it('deletes notifications with no activity in the window, read or not, in batches', function (): void {
    $old = UserNotification::factory()->count(3)->create(['updated_at' => now()->subDays(8)]);
    $oldRead = UserNotification::factory()->read()->create(['updated_at' => now()->subDays(10)]);
    $recent = UserNotification::factory()->create(['created_at' => now()->subDays(9), 'updated_at' => now()->subDays(2)]);

    $deleted = app(PruneNotificationsAction::class)->execute(7, batchSize: 1);

    expect($deleted)->toBe(4)
        ->and(UserNotification::query()->whereKey([...$old->modelKeys(), $oldRead->id])->exists())->toBeFalse()
        ->and($recent->fresh())->not->toBeNull();
});
