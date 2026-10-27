<?php

declare(strict_types=1);

namespace Domain\Agent\Actions\Account;

use Domain\Agent\Jobs\PurgeAgentJob;
use Domain\Agent\Models\Agent;
use Illuminate\Database\Eloquent\Collection;

/**
 * Queues {@see PurgeAgentJob} again for every agent deleted more than
 * {@see self::STALE_AFTER_HOURS} hours ago that is still there. The purge
 * normally ends within the hour, retries included; an agent left after a day
 * means every try failed, and without this its chats and files would stay
 * forever. The purge deletes only what is left, so a second run is safe.
 */
class QueueStaleAgentPurgesAction
{
    /** Hours after the delete before a remaining agent counts as stuck. */
    public const int STALE_AFTER_HOURS = 24;

    /**
     * @return int how many purges were queued
     */
    public function execute(): int
    {
        $queued = 0;

        Agent::query()->onlyTrashed()
            ->where('deleted_at', '<', now()->subHours(self::STALE_AFTER_HOURS))
            ->select('id')
            ->chunkById(500, function (Collection $agents) use (&$queued): void {
                foreach ($agents as $agent) {
                    PurgeAgentJob::dispatch($agent->id);
                    $queued++;
                }
            });

        return $queued;
    }
}
