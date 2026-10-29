<?php

use App\Identity\Workspace\Models\User;
use Domain\Notification\Models\UserNotification;

/**
 * Marking one of the person's notifications read. A notification is one
 * person's: a teammate's, even in the same workspace, is a 404.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    createAgent($this->owner);
});

it('marks one notification read', function (): void {
    $notification = UserNotification::factory()->for($this->owner)->create();

    $this->actingAs($this->owner)->patchJson('/my/notifications/'.$notification->external_id)->assertNoContent();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it("answers 404 for a teammate's notification and leaves it unread", function (): void {
    $theirs = UserNotification::factory()->for(User::factory()->employee($this->owner)->create())->create();

    $this->actingAs($this->owner)->patchJson('/my/notifications/'.$theirs->external_id)->assertNotFound();

    expect($theirs->fresh()->read_at)->toBeNull();
});
