<?php

declare(strict_types=1);

namespace App\Admin\Issue\Actions;

use App\Admin\Issue\Models\Issue;
use App\Admin\Issue\Models\IssueOccurrence;

/**
 * Deletes what Radar no longer needs: occurrences older than the
 * retention, then the issues not seen since then (their last occurrences
 * go with them). Deleted in chunks, so a large backlog never holds one
 * long lock. Returns how many issues were deleted.
 */
class PruneIssuesAction
{
    private const int CHUNK = 5000;

    public function execute(int $days): int
    {
        $before = now()->subDays($days);

        do {
            $deleted = IssueOccurrence::query()
                ->whereIn('id', IssueOccurrence::query()->where('occurred_at', '<', $before)->limit(self::CHUNK)->select('id'))
                ->delete();
        } while ($deleted === self::CHUNK);

        $issues = 0;

        do {
            $deleted = Issue::query()
                ->whereIn('id', Issue::query()->where('last_seen_at', '<', $before)->limit(self::CHUNK)->select('id'))
                ->delete();
            $issues += $deleted;
        } while ($deleted === self::CHUNK);

        return $issues;
    }
}
