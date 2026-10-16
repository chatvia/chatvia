<?php

declare(strict_types=1);

namespace Domain\Notification\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Notification\Events\UserNotificationsRead;

/**
 * Marks every unread notification of one person read and clears the badge in
 * all their tabs.
 */
class MarkAllNotificationsReadAction
{
    /**
     * @return int how many were marked
     */
    public function execute(User $user): int
    {
        $marked = $user->userNotifications()->unread()->update(['read_at' => now()]);

        if ($marked > 0) {
            UserNotificationsRead::dispatch($user->id, null, 0);
        }

        return $marked;
    }
}
