<?php

declare(strict_types=1);

namespace Domain\Notification\Actions;

use Domain\Notification\Models\UserNotification;

/**
 * Deletes notifications with no activity in the retention window, read or
 * not. Rows go in batches, so one run never holds a huge delete; the
 * `updated_at` index finds them.
 */
class PruneNotificationsAction
{
    /**
     * @return int how many rows were deleted
     */
    public function execute(int $days, int $batchSize = 10000): int
    {
        $cutoff = now()->subDays($days);
        $deleted = 0;

        do {
            $batch = UserNotification::query()
                ->whereIn('id', UserNotification::query()->select('id')->where('updated_at', '<', $cutoff)->limit($batchSize))
                ->delete();

            $deleted += $batch;
        } while ($batch > 0);

        return $deleted;
    }
}
