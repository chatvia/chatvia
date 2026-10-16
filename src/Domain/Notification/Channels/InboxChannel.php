<?php

declare(strict_types=1);

namespace Domain\Notification\Channels;

use App\Identity\Workspace\Models\User;
use Domain\Notification\Actions\PushUserNotificationAction;
use Domain\Notification\Notifications\InboxNotification;
use Illuminate\Notifications\Notification;

/**
 * The Laravel notification channel behind the dashboard's notification
 * center: an {@see InboxNotification} sent to a staff account is stored in
 * that person's inbox and pushed to their open tabs.
 */
final readonly class InboxChannel
{
    public function __construct(
        private PushUserNotificationAction $push,
    ) {}

    public function send(object $notifiable, Notification $notification): void
    {
        if (! $notifiable instanceof User || ! $notification instanceof InboxNotification) {
            return;
        }

        $this->push->execute($notifiable, $notification->toInbox($notifiable));
    }
}
