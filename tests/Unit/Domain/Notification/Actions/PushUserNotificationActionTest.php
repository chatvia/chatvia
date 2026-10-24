<?php

use App\Identity\Workspace\Models\User;
use Domain\Notification\Actions\PushUserNotificationAction;
use Domain\Notification\Data\NotificationContentData;
use Domain\Notification\Data\NotificationMessageData;
use Domain\Notification\Enums\NotificationCategory;
use Domain\Notification\Enums\NotificationKind;
use Domain\Notification\Events\UserNotificationPushed;
use Domain\Notification\Models\UserNotification;
use Illuminate\Support\Facades\Event;

/**
 * Pushing a notification stores it with its category and tells the person's
 * tabs. Notifications with the same group key gather into the unread row
 * (one person's only); once that row is read, a new one starts.
 */
function pushNoteMessage(string $subject, ?string $groupKey = null, NotificationKind $kind = NotificationKind::SourceTrained): NotificationMessageData
{
    return new NotificationMessageData($kind, new NotificationContentData(url: '/my/agents/a/sources/files', subject: $subject), $groupKey);
}

beforeEach(function (): void {
    Event::fake([UserNotificationPushed::class]);
    $this->user = User::factory()->create();
});

it('stores a notification with its category and tells the person\'s tabs it is new', function (): void {
    $notification = app(PushUserNotificationAction::class)->execute($this->user, pushNoteMessage('guide.pdf'));

    expect($notification->user_id)->toBe($this->user->id)
        ->and($notification->kind)->toBe(NotificationKind::SourceTrained)
        ->and($notification->category)->toBe(NotificationCategory::Training)
        ->and($notification->content->subject)->toBe('guide.pdf')
        ->and($notification->count)->toBe(1)
        ->and($notification->read_at)->toBeNull();

    Event::assertDispatched(UserNotificationPushed::class, fn (UserNotificationPushed $event): bool => $event->userId === $this->user->id
        && $event->isNew
        && $event->notification->id === $notification->external_id);
});

it('gathers notifications with the same key into the unread row, newest content first', function (): void {
    $push = app(PushUserNotificationAction::class);

    $first = $push->execute($this->user, pushNoteMessage('a.pdf', 'training:agent-1'));
    $this->travel(5)->minutes();
    $second = $push->execute($this->user, pushNoteMessage('b.pdf', 'training:agent-1'));

    expect($second->id)->toBe($first->id)
        ->and($second->count)->toBe(2)
        ->and($second->content->subject)->toBe('b.pdf')
        ->and($second->updated_at->gt($first->updated_at))->toBeTrue()
        ->and(UserNotification::query()->count())->toBe(1);

    Event::assertDispatched(UserNotificationPushed::class, fn (UserNotificationPushed $event): bool => ! $event->isNew && $event->notification->count === 2);
});

it('starts a new row once the gathered one was read', function (): void {
    $push = app(PushUserNotificationAction::class);

    $first = $push->execute($this->user, pushNoteMessage('a.pdf', 'training:agent-1'));
    $first->update(['read_at' => now()]);
    $second = $push->execute($this->user, pushNoteMessage('b.pdf', 'training:agent-1'));

    expect($second->id)->not->toBe($first->id)
        ->and($second->count)->toBe(1)
        ->and(UserNotification::query()->count())->toBe(2);
});

it('never gathers one person\'s notification into another\'s', function (): void {
    $other = User::factory()->create();
    $push = app(PushUserNotificationAction::class);

    $push->execute($this->user, pushNoteMessage('a.pdf', 'training:agent-1'));
    $push->execute($other, pushNoteMessage('a.pdf', 'training:agent-1'));

    expect(UserNotification::query()->where('user_id', $this->user->id)->sole()->count)->toBe(1)
        ->and(UserNotification::query()->where('user_id', $other->id)->sole()->count)->toBe(1);
});

it('keeps notifications without a key apart', function (): void {
    $push = app(PushUserNotificationAction::class);

    $push->execute($this->user, pushNoteMessage('a.pdf', kind: NotificationKind::SourceTrainingFailed));
    $push->execute($this->user, pushNoteMessage('b.pdf', kind: NotificationKind::SourceTrainingFailed));

    expect(UserNotification::query()->count())->toBe(2);
});
