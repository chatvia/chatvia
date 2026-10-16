<?php

declare(strict_types=1);

namespace Domain\Notification\Actions;

use Domain\Notification\Events\UserNotificationsRead;
use Domain\Notification\Models\UserNotification;

/**
 * Marks a group's unread notifications read for everyone who has one, once
 * what they announced is dealt with — for example a waiting visitor a
 * teammate has taken over. Each person's tabs are told, with their unread
 * count left.
 */
class ClearNotificationGroupAction
{
    public function execute(string $groupKey): void
    {
        $open = UserNotification::query()
            ->where('group_key', $groupKey)
            ->unread()
            ->get(['id', 'external_id', 'user_id']);

        if ($open->isEmpty()) {
            return;
        }

        UserNotification::query()->whereKey($open->modelKeys())->update(['read_at' => now()]);

        foreach ($open->groupBy('user_id') as $userId => $rows) {
            UserNotificationsRead::dispatch(
                (int) $userId,
                $rows->pluck('external_id')->values()->all(),
                UserNotification::query()->where('user_id', $userId)->unread()->count(),
            );
        }
    }
}
