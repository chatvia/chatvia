<?php

use App\Identity\Workspace\Models\User;
use Domain\Notification\Channels\InboxChannel;
use Domain\Notification\Data\NotificationContentData;
use Domain\Notification\Data\NotificationMessageData;
use Domain\Notification\Enums\NotificationKind;
use Domain\Notification\Notifications\InboxNotification;

/**
 * A notification goes to the person's inbox only while they keep the bell
 * on for its category; their other categories do not matter.
 */
function inUsageNotification(): InboxNotification
{
    return new class extends InboxNotification
    {
        public function kind(): NotificationKind
        {
            return NotificationKind::RateLimitExceeded;
        }

        public function toInbox(User $notifiable): NotificationMessageData
        {
            return $this->message(new NotificationContentData(url: '/dashboard'));
        }
    };
}

it('goes to the inbox only while the bell of its category is on', function (array $preferences, array $channels): void {
    $person = User::factory()->create(['notification_preferences' => $preferences]);

    expect(inUsageNotification()->via($person))->toBe($channels);
})->with([
    'the defaults' => [[], [InboxChannel::class]],
    'its category switched off' => [['usage' => ['bell' => false, 'desktop' => false, 'sound' => false]], []],
    'another category switched off' => [['leads' => ['bell' => false, 'desktop' => false, 'sound' => false]], [InboxChannel::class]],
]);
