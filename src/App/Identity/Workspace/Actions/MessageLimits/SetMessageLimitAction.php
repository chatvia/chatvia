<?php

namespace App\Identity\Workspace\Actions\MessageLimits;

use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\MessageAllowance;

/**
 * Sets or clears the monthly message limit of a workspace or a chatter. Null
 * removes the limit. A number keeps what was already used this month, so
 * lowering the limit can block the account right away.
 */
class SetMessageLimitAction
{
    public function __construct(
        private readonly MessageAllowance $allowance,
    ) {}

    public function execute(User $user, ?int $monthlyLimit): void
    {
        if ($monthlyLimit === null) {
            MessageLimit::query()->where('user_id', $user->id)->delete();
        } else {
            $limit = MessageLimit::query()->firstOrNew(['user_id' => $user->id]);

            if (! $limit->exists || ! $limit->isCurrentPeriod()) {
                $limit->period_starts_at = now()->startOfMonth();
                $limit->messages_used = 0;
            }

            $limit->monthly_limit = $monthlyLimit;
            $limit->save();
        }

        $this->allowance->forget($user->id);
    }
}
