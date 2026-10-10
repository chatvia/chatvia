<?php

declare(strict_types=1);

namespace App\Identity\Workspace\Actions\MessageLimits;

use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Services\MessageAllowance;

/**
 * Counts one message against an account's monthly limit, restarting the count
 * when a new month has begun. Does nothing for an account without a limit.
 */
class RecordMessageUsageAction
{
    public function __construct(
        private readonly MessageAllowance $allowance,
    ) {}

    public function execute(int $userId): void
    {
        $limit = $this->allowance->limitFor($userId);

        if (! $limit instanceof MessageLimit) {
            return;
        }

        if ($limit->isCurrentPeriod()) {
            $limit->increment('messages_used');
        } else {
            $limit->update(['period_starts_at' => now()->startOfMonth(), 'messages_used' => 1]);
        }

        // Below the limit the cached "may send" stays; the message that
        // reaches it drops the cache so the next turn is refused.
        if ($limit->reached()) {
            $this->allowance->forget($userId);
        }
    }
}
