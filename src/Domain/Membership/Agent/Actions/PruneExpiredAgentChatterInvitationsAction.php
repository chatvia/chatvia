<?php

declare(strict_types=1);

namespace Domain\Membership\Agent\Actions;

use Domain\Membership\Agent\Models\AgentChatterInvitation;

/**
 * Deletes chatter invitations whose link expired more than
 * {@see self::KEEP_EXPIRED_DAYS} days ago. An expired link no longer works;
 * the row is kept a while so the chatter list can still say the link
 * expired. Rows go in batches; the index on `expires_at` finds them.
 */
class PruneExpiredAgentChatterInvitationsAction
{
    /** Days an expired invitation is kept before it is deleted. */
    public const int KEEP_EXPIRED_DAYS = 30;

    /**
     * @return int how many invitations were deleted
     */
    public function execute(int $batchSize = 5000): int
    {
        $cutoff = now()->subDays(self::KEEP_EXPIRED_DAYS);
        $deleted = 0;

        do {
            $batch = AgentChatterInvitation::query()
                ->whereIn('id', AgentChatterInvitation::query()->select('id')->where('expires_at', '<', $cutoff)->limit($batchSize))
                ->delete();

            $deleted += $batch;
        } while ($batch > 0);

        return $deleted;
    }
}
