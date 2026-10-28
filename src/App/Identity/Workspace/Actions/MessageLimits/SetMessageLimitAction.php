<?php

namespace App\Identity\Workspace\Actions\MessageLimits;

use App\Identity\Chatter\Models\Chatter;
use App\Identity\Workspace\Models\MessageLimit;
use App\Identity\Workspace\Models\Workspace;
use App\Identity\Workspace\Services\MessageAllowance;

/**
 * Sets or clears the monthly message limit of a workspace or a chatter. Null
 * removes the limit; a workspace keeps its row and this month's count, so
 * setting a limit again later in the month starts from it. A number keeps what was
 * already used this month, so lowering the limit can block the account right
 * away.
 */
class SetMessageLimitAction
{
    public function __construct(
        private readonly MessageAllowance $allowance,
    ) {}

    /**
     * @param  Workspace|Chatter  $owner  a workspace or a chatter
     */
    public function execute(Chatter|Workspace $owner, ?int $monthlyLimit): void
    {
        $column = $owner instanceof Chatter ? 'chatter_id' : 'workspace_id';

        if ($monthlyLimit === null && $owner instanceof Chatter) {
            MessageLimit::query()->where($column, $owner->id)->delete();
        } elseif ($monthlyLimit === null) {
            MessageLimit::query()->where($column, $owner->id)->update(['monthly_limit' => null]);
        } else {
            $limit = MessageLimit::query()->firstOrNew([$column => $owner->id]);

            if (! $limit->exists || ! $limit->isCurrentPeriod()) {
                $limit->period_starts_at = now()->startOfMonth();
                $limit->messages_used = 0;
            }

            $limit->monthly_limit = $monthlyLimit;
            $limit->save();
        }

        $owner instanceof Chatter
            ? $this->allowance->forgetChatter($owner->id)
            : $this->allowance->forget($owner->id);
    }
}
