<?php

namespace Domain\Billing\Actions\ChatterAllowance;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Models\UserCredit;
use Domain\Billing\Services\ChatterMessageAllowance;
use Illuminate\Support\Facades\DB;

/**
 * Returns the chatter's credit row for the current month, opening it (with an
 * empty usage row) when the month rolled over. The cap is `$cap` when given
 * (the admin sets one) or carries forward from the latest earlier row. Returns
 * null when the chatter has no cap at all. Windows renew lazily, on the first
 * message or limit change of a new month, so no scheduler is needed.
 */
class OpenChatterCreditPeriodAction
{
    public function __construct(
        private readonly ChatterMessageAllowance $allowance,
    ) {}

    public function execute(User $chatter, ?int $cap = null): ?UserCredit
    {
        $current = $this->allowance->currentCredit($chatter);

        if ($current instanceof UserCredit) {
            return $current;
        }

        $cap ??= $this->allowance->latestCredit($chatter)?->messages;

        if ($cap === null) {
            return null;
        }

        $credit = DB::transaction(function () use ($chatter, $cap): UserCredit {
            /** @var UserCredit $credit */
            $credit = UserCredit::query()->create([
                'user_id' => $chatter->id,
                'messages' => $cap,
                'started_at' => now()->startOfMonth(),
                'ends_at' => now()->endOfMonth(),
            ]);

            $credit->usage()->create();

            return $credit;
        });

        // A fresh window starts at 0 used: reset the cached send flag.
        $this->allowance->forget($chatter);

        return $credit->load('usage');
    }
}
