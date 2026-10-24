<?php

use App\Identity\Workspace\Models\User;
use Domain\Notification\Models\UserNotification;

/**
 * Marking all of the person's notifications read, and nobody else's.
 */
it("marks all of the person's notifications read", function (): void {
    $owner = User::factory()->create();
    UserNotification::factory()->for($owner)->count(3)->create();
    $theirs = UserNotification::factory()->create();

    $this->actingAs($owner)->postJson('/my/notifications/read-all')->assertNoContent();

    expect($owner->userNotifications()->unread()->count())->toBe(0)
        ->and($theirs->fresh()->read_at)->toBeNull();
});
