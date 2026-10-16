<?php

declare(strict_types=1);

namespace Domain\Notification\Actions;

use Domain\Notification\Events\UserNotificationsRead;
use Domain\Notification\Models\UserNotification;

/**
 * Marks one of a person's notifications read (they opened it) and tells their
 * other tabs, with the unread count left. A notification already read is left
 * as it is.
 */
class MarkNotificationReadAction
{
    public function execute(UserNotification $notification): void
    {
        $marked = UserNotification::query()
            ->whereKey($notification->id)
            ->unread()
            ->update(['read_at' => now()]);

        if ($marked === 0) {
            return;
        }

        UserNotificationsRead::dispatch(
            $notification->user_id,
            [$notification->external_id],
            UserNotification::query()->where('user_id', $notification->user_id)->unread()->count(),
        );
    }
}
