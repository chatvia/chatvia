<?php

use App\Identity\Workspace\Models\User;
use Domain\Notification\Models\UserNotification;
use Inertia\Testing\AssertableInertia;

/**
 * HandleInertiaRequests shares the person's unread notification count and
 * alert switches with every dashboard page, for the bell.
 */
it('shares the unread count and the alert switches with every dashboard page', function (): void {
    $owner = User::factory()->create();
    createAgent($owner);
    UserNotification::factory()->for($owner)->count(2)->create();
    UserNotification::factory()->for($owner)->read()->create();

    $this->actingAs($owner)->get('/dashboard')
        ->assertOk()
        ->assertInertia(fn (AssertableInertia $page): AssertableInertia => $page
            ->where('notifications.unread', 2)
            ->where('notifications.preferences.handoffs.sound', true));
});
