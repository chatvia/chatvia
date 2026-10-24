<?php

use App\Identity\Workspace\Models\User;
use Domain\Notification\Enums\NotificationKind;
use Domain\Notification\Models\UserNotification;
use Inertia\Testing\AssertableInertia;

/**
 * The Notifications page (newest activity first) and marking one of the
 * person's notifications read. A notification is one person's: a
 * teammate's, even in the same workspace, is a 404.
 */
beforeEach(function (): void {
    $this->owner = User::factory()->create();
    createAgent($this->owner);
});

it('renders the Notifications page with the newest activity first', function (): void {
    $older = UserNotification::factory()->for($this->owner)->create(['updated_at' => now()->subHour()]);
    $newer = UserNotification::factory()->for($this->owner)->ofKind(NotificationKind::LeadCaptured)->create();

    $this->actingAs($this->owner)->get('/my/notifications')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->component('notifications/Index')
            ->where('filter', 'all')
            ->where('feed.unread', 2)
            ->has('feed.notifications', 2)
            ->where('feed.notifications.0.id', $newer->external_id)
            ->where('feed.notifications.0.kind', 'lead_captured')
            ->where('feed.notifications.0.category', 'leads')
            ->where('feed.notifications.1.id', $older->external_id));
});

it('marks one notification read', function (): void {
    $notification = UserNotification::factory()->for($this->owner)->create();

    $this->actingAs($this->owner)->patchJson('/my/notifications/'.$notification->external_id)->assertNoContent();

    expect($notification->fresh()->read_at)->not->toBeNull();
});

it("answers 404 for a teammate's notification and leaves it unread", function (): void {
    // Same workspace, so the workspace scope alone would let it through.
    $theirs = UserNotification::factory()->for(User::factory()->employee($this->owner)->create())->create();

    $this->actingAs($this->owner)->patchJson('/my/notifications/'.$theirs->external_id)->assertNotFound();

    expect($theirs->fresh()->read_at)->toBeNull();
});
