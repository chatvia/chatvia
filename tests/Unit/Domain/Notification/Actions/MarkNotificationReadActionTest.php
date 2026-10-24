<?php

use App\Identity\Workspace\Models\User;
use Domain\Notification\Actions\MarkNotificationReadAction;
use Domain\Notification\Events\UserNotificationsRead;
use Domain\Notification\Models\UserNotification;
use Illuminate\Support\Facades\Event;

/**
 * Marking one notification read tells the person's tabs how many are left;
 * one already read stays as it was, without a broadcast.
 */
beforeEach(function (): void {
    Event::fake([UserNotificationsRead::class]);
});

it('marks one notification read and tells the person\'s tabs the count left', function (): void {
    $user = User::factory()->create();
    $opened = UserNotification::factory()->for($user)->create();
    UserNotification::factory()->for($user)->create();

    app(MarkNotificationReadAction::class)->execute($opened);

    expect($opened->fresh()->read_at)->not->toBeNull();
    Event::assertDispatched(UserNotificationsRead::class, fn (UserNotificationsRead $event): bool => $event->userId === $user->id
        && $event->ids === [$opened->external_id]
        && $event->unread === 1);
});

it('leaves a notification that is already read as it is, without a broadcast', function (): void {
    $read = UserNotification::factory()->read()->create(['read_at' => now()->subDay()]);
    $readAt = $read->read_at;

    app(MarkNotificationReadAction::class)->execute($read);

    expect($read->fresh()->read_at->equalTo($readAt))->toBeTrue();
    Event::assertNotDispatched(UserNotificationsRead::class);
});
