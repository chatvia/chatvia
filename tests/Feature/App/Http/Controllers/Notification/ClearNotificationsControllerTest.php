<?php

use App\Identity\Workspace\Models\User;
use Domain\Notification\Models\UserNotification;

/**
 * Clearing all of the person's notifications, and nobody else's.
 */
it("clears all of the person's notifications and nobody else's", function (): void {
    $owner = User::factory()->create();
    UserNotification::factory()->for($owner)->count(2)->create();
    $theirs = UserNotification::factory()->create();

    $this->actingAs($owner)->deleteJson('/my/notifications')->assertNoContent();

    expect($owner->userNotifications()->count())->toBe(0)
        ->and($theirs->fresh())->not->toBeNull();
});
