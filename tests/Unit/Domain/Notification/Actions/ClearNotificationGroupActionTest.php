<?php

use Domain\Notification\Actions\ClearNotificationGroupAction;
use Domain\Notification\Events\UserNotificationsRead;
use Domain\Notification\Models\UserNotification;
use Illuminate\Support\Facades\Event;

/**
 * Clearing a group (e.g. a handoff someone took over) marks its unread rows
 * read for everyone who has them and tells each person's tabs.
 */
it('marks a group read for everyone who has it and tells each of them', function (): void {
    Event::fake([UserNotificationsRead::class]);

    $first = UserNotification::factory()->create(['group_key' => 'handoff:conv-1']);
    $second = UserNotification::factory()->create(['group_key' => 'handoff:conv-1']);
    $otherGroup = UserNotification::factory()->create(['group_key' => 'handoff:conv-2']);

    app(ClearNotificationGroupAction::class)->execute('handoff:conv-1');

    expect($first->fresh()->read_at)->not->toBeNull()
        ->and($second->fresh()->read_at)->not->toBeNull()
        ->and($otherGroup->fresh()->read_at)->toBeNull();

    Event::assertDispatchedTimes(UserNotificationsRead::class, 2);
    Event::assertDispatched(UserNotificationsRead::class, fn (UserNotificationsRead $event): bool => $event->userId === $first->user_id
        && $event->ids === [$first->external_id]
        && $event->unread === 0);
});

it('does nothing for a group nobody has unread', function (): void {
    Event::fake([UserNotificationsRead::class]);
    UserNotification::factory()->read()->create(['group_key' => 'handoff:conv-1']);

    app(ClearNotificationGroupAction::class)->execute('handoff:conv-1');

    Event::assertNotDispatched(UserNotificationsRead::class);
});
