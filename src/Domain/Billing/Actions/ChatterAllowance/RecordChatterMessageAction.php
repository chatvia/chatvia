<?php

namespace Domain\Billing\Actions\ChatterAllowance;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Services\ChatterMessageAllowance;

/**
 * Counts one message against a chatter's monthly cap, opening the new month's
 * window first when it rolled over. Does nothing for non-chatters and for
 * chatters without a cap. Called when a chatter's message is stored.
 */
class RecordChatterMessageAction
{
    public function __construct(
        private readonly ChatterMessageAllowance $allowance,
        private readonly OpenChatterCreditPeriodAction $openPeriod,
    ) {}

    public function execute(User $chatter): void
    {
        if (! $chatter->isChatter()) {
            return;
        }

        $credit = $this->openPeriod->execute($chatter);

        if ($credit === null) {
            return;
        }

        $credit->usage()->increment('messages', 1);

        // Flip the cached flag to "blocked" the moment this message uses up the
        // cap. Below the cap the flag is left alone, so steady sends stay
        // cached.
        if ((int) ($credit->usage?->messages ?? 0) + 1 >= $credit->messages) {
            $this->allowance->forget($chatter);
        }
    }
}
