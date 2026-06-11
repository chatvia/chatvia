<?php

namespace App\Http\Controllers\Billing\Addons;

use App\Http\Requests\Billing\UpdateAutoRechargeRequest;
use Illuminate\Http\RedirectResponse;

class UpdateAutoRechargeController
{
    public function __invoke(UpdateAutoRechargeRequest $request): RedirectResponse
    {
        $data = $request->toData();

        // Trivial single-Eloquent-call write — no Action needed.
        $data->user->accountBillingOrFail()->update([
            'auto_recharge_enabled' => $data->enabled,
            'auto_recharge_threshold' => $data->threshold,
            'auto_recharge_amount' => $data->amount,
        ]);

        return back();
    }
}
