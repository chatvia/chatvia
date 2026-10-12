<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Actions\MessageLimits;

use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Services\MessageAllowance;

/**
 * Counts one message against a monthly limit (a workspace's or a chatter's,
 * looked up by the caller through {@see MessageAllowance}), restarting the
 * count when a new month has begun. Does nothing without a limit.
 *
 * The count and the month rollover are one UPDATE … RETURNING: the database
 * decides from the stored period, so two replies at once never both restart
 * the month or lose a count, and the returned values tell whether this
 * message reached the limit.
 */
class RecordMessageUsageAction
{
    public function __construct(
        private readonly MessageAllowance $allowance,
    ) {}

    public function execute(?MessageLimit $limit): void
    {
        if (! $limit instanceof MessageLimit) {
            return;
        }

        $now = now();
        $monthStart = $now->copy()->startOfMonth();

        $row = $limit->getConnection()->selectOne(<<<'SQL'
            UPDATE message_limits SET
                messages_used = CASE WHEN period_starts_at >= ? THEN messages_used + 1 ELSE 1 END,
                period_starts_at = GREATEST(period_starts_at, ?),
                updated_at = ?
            WHERE id = ?
            RETURNING messages_used, monthly_limit, period_starts_at, updated_at
        SQL, [$monthStart, $monthStart, $now, $limit->id]);

        // The limit was removed in the meantime: nothing left to count.
        if ($row === null) {
            return;
        }

        $limit->forceFill((array) $row)->syncOriginal();

        // Below the limit the cached "may send" stays; the message that
        // reaches it drops the cache so the next turn is refused.
        if ($limit->reached()) {
            $this->allowance->forgetLimit($limit);
        }
    }
}
