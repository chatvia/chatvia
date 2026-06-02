<?php

namespace Domain\Billing\Actions\Account;

use Domain\Billing\Data\UpdateAutoRechargeData;

class UpdateAutoRechargeAction
{
    public function execute(UpdateAutoRechargeData $data): void
    {
        $billing = $data->user->accountBillingOrFail();

        $billing->update([
            'auto_recharge_enabled' => $data->enabled,
            'auto_recharge_threshold' => $data->threshold,
            'auto_recharge_amount' => $data->amount,
        ]);
    }
}
