<?php

declare(strict_types=1);

namespace Domain\Notification\Actions;

use App\Identity\Workspace\Models\User;
use Domain\Notification\Events\UserNotificationsCleared;

/**
 * Deletes every notification of one person ("Clear all") and empties the
 * list and badge in all their tabs. Read or not, they are gone; new ones
 * still arrive.
 */
class ClearNotificationsAction
{
    /**
     * @return int how many were deleted
     */
    public function execute(User $user): int
    {
        $deleted = $user->userNotifications()->delete();

        if ($deleted > 0) {
            UserNotificationsCleared::dispatch($user->id);
        }

        return $deleted;
    }
}
