<?php

use App\Identity\Workspace\Models\User;
use Domain\Notification\Actions\MarkAllNotificationsReadAction;
use Domain\Notification\Events\UserNotificationsRead;
use Domain\Notification\Models\UserNotification;
use Illuminate\Support\Facades\Event;

beforeEach(function (): void {
    Event::fake([UserNotificationsRead::class]);
});

it('marks every unread notification of one person read and clears their badge', function (): void {
    $user = User::factory()->create();
    $other = UserNotification::factory()->create();
    UserNotification::factory()->for($user)->count(2)->create();

    expect(app(MarkAllNotificationsReadAction::class)->execute($user))->toBe(2)
        ->and($user->userNotifications()->unread()->count())->toBe(0)
        ->and($other->fresh()->read_at)->toBeNull();

    Event::assertDispatched(UserNotificationsRead::class, fn (UserNotificationsRead $event): bool => $event->userId === $user->id
        && $event->ids === null
        && $event->unread === 0);
});

it('sends nothing when there was nothing to mark', function (): void {
    expect(app(MarkAllNotificationsReadAction::class)->execute(User::factory()->create()))->toBe(0);

    Event::assertNotDispatched(UserNotificationsRead::class);
});
