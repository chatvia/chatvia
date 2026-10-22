<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Actions\Sessions;

use App\Identity\Workspace\Models\UserKnownDevice;

/**
 * Deletes the sign-in devices (`user_known_devices`) nobody signed in from
 * for {@see self::STALE_AFTER_DAYS} days, so the table does not keep every
 * browser and network a person ever used. A device signing in again after
 * that counts as new, and its owner gets the new-device email once more.
 * Rows go in batches, so one run never holds a huge delete; the index on
 * `last_seen_at` finds them.
 */
class PruneStaleKnownDevicesAction
{
    /** Days without a sign-in after which a device is forgotten. */
    public const int STALE_AFTER_DAYS = 180;

    /**
     * @return int how many devices were deleted
     */
    public function execute(int $batchSize = 5000): int
    {
        $cutoff = now()->subDays(self::STALE_AFTER_DAYS);
        $deleted = 0;

        do {
            $batch = UserKnownDevice::query()
                ->whereIn('id', UserKnownDevice::query()->select('id')->where('last_seen_at', '<', $cutoff)->limit($batchSize))
                ->delete();

            $deleted += $batch;
        } while ($batch > 0);

        return $deleted;
    }
}
