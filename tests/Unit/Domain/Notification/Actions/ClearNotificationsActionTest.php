<?php

use App\Identity\Workspace\Models\User;
use Domain\Notification\Actions\ClearNotificationsAction;
use Domain\Notification\Events\UserNotificationsCleared;
use Domain\Notification\Models\UserNotification;
use Illuminate\Support\Facades\Event;

/**
 * Clearing deletes all of one person's notifications, read or not, and tells
 * their tabs; with nothing to clear it sends nothing.
 */
beforeEach(function (): void {
    Event::fake([UserNotificationsCleared::class]);
});

it('deletes every notification of one person, read or not, and empties their tabs', function (): void {
    $user = User::factory()->create();
    UserNotification::factory()->for($user)->count(2)->create();
    UserNotification::factory()->for($user)->read()->create();
    $theirs = UserNotification::factory()->create();

    expect(app(ClearNotificationsAction::class)->execute($user))->toBe(3)
        ->and($user->userNotifications()->count())->toBe(0)
        ->and($theirs->fresh())->not->toBeNull();

    Event::assertDispatched(UserNotificationsCleared::class, fn (UserNotificationsCleared $event): bool => $event->userId === $user->id);
});

it('sends nothing when there was nothing to clear', function (): void {
    expect(app(ClearNotificationsAction::class)->execute(User::factory()->create()))->toBe(0);

    Event::assertNotDispatched(UserNotificationsCleared::class);
});
