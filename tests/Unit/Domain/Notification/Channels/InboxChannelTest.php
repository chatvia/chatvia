<?php

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\User;
use Domain\Notification\Channels\InboxChannel;
use Domain\Notification\Data\NotificationContentData;
use Domain\Notification\Data\NotificationMessageData;
use Domain\Notification\Enums\NotificationKind;
use Domain\Notification\Models\UserNotification;
use Domain\Notification\Notifications\InboxNotification;
use Illuminate\Notifications\Notification;

/**
 * The notification channel of the notification center stores an inbox
 * notification in a staff account's inbox. Anyone else, and any other kind
 * of notification, it leaves alone.
 */
function icNotification(): InboxNotification
{
    return new class extends InboxNotification
    {
        public function kind(): NotificationKind
        {
            return NotificationKind::LeadCaptured;
        }

        public function toInbox(User $notifiable): NotificationMessageData
        {
            return $this->message(new NotificationContentData(url: '/dashboard', subject: 'Book a demo'));
        }
    };
}

it("stores an inbox notification in the person's inbox", function (): void {
    $person = User::factory()->create();

    app(InboxChannel::class)->send($person, icNotification());

    $stored = UserNotification::query()->where('user_id', $person->id)->sole();
    expect($stored->kind)->toBe(NotificationKind::LeadCaptured)
        ->and($stored->content->subject)->toBe('Book a demo');
});

it('stores nothing for a chatter or for a notification that is not an inbox one', function (Closure $notifiable, Closure $notification): void {
    app(InboxChannel::class)->send($notifiable(), $notification());

    expect(UserNotification::query()->exists())->toBeFalse();
})->with([
    'a chatter' => [fn (): Chatter => Chatter::factory()->create(), fn (): InboxNotification => icNotification()],
    'a plain notification' => [fn (): User => User::factory()->create(), fn (): Notification => new class extends Notification {}],
]);
