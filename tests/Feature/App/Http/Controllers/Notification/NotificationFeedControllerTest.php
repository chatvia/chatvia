<?php

use App\Identity\Workspace\Models\User;
use Domain\Notification\Models\UserNotification;

/**
 * The notification feed the bell loads holds the person's own notifications
 * only.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    createAgent($this->owner);
});

it("never lists someone else's notifications", function (): void {
    UserNotification::factory()->create();

    $this->actingAs($this->owner)->getJson('/my/notifications/feed')
        ->assertOk()
        ->assertJsonCount(0, 'notifications')
        ->assertJsonPath('unread', 0);
});
