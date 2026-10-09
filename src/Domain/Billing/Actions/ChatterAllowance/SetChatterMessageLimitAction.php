<?php

namespace Domain\Billing\Actions\ChatterAllowance;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Models\UserCredit;
use Domain\Billing\Services\ChatterMessageAllowance;

/**
 * The admin sets or clears a chatter's monthly message cap. Null clears it:
 * the chatter's credit rows are deleted, which means no limit. A number is
 * written on the current month's row, keeping what was already used this
 * month. Does nothing for non-chatters.
 */
class SetChatterMessageLimitAction
{
    public function __construct(
        private readonly ChatterMessageAllowance $allowance,
        private readonly OpenChatterCreditPeriodAction $openPeriod,
    ) {}

    public function execute(User $chatter, ?int $messages): void
    {
        if (! $chatter->isChatter()) {
            return;
        }

        if ($messages === null) {
            UserCredit::query()->where('user_id', $chatter->id)->delete();
        } else {
            $this->openPeriod->execute($chatter, $messages)?->update(['messages' => $messages]);
        }

        $this->allowance->forget($chatter);
    }
}
