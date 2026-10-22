<?php

declare(strict_types=1);

namespace Domain\Membership\Team\Actions\Invitations;

use Domain\Membership\Team\Models\Invitation;

/**
 * Deletes team invitations whose link expired more than
 * {@see self::KEEP_EXPIRED_DAYS} days ago. A team invitation has no expiry
 * column: it expires {@see Invitation::TTL_DAYS} days after `created_at`,
 * which a resend moves forward. The row is kept a while so an old link still
 * opens the "invitation expired" page instead of a missing one. Rows go in
 * batches.
 */
class PruneExpiredTeamInvitationsAction
{
    /** Days an expired invitation is kept before it is deleted. */
    public const int KEEP_EXPIRED_DAYS = 30;

    /**
     * @return int how many invitations were deleted
     */
    public function execute(int $batchSize = 5000): int
    {
        $cutoff = now()->subDays(Invitation::TTL_DAYS + self::KEEP_EXPIRED_DAYS);
        $deleted = 0;

        do {
            $batch = Invitation::query()
                ->whereIn('id', Invitation::query()->select('id')->where('created_at', '<', $cutoff)->limit($batchSize))
                ->delete();

            $deleted += $batch;
        } while ($batch > 0);

        return $deleted;
    }
}
