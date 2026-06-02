<?php

namespace App\Http\Controllers\Billing\Addons;

use App\Http\Requests\Billing\UpdateAutoRechargeRequest;
use Domain\Billing\Actions\Account\UpdateAutoRechargeAction;
use Illuminate\Http\RedirectResponse;

class UpdateAutoRechargeController
{
    public function __invoke(
        UpdateAutoRechargeRequest $request,
        UpdateAutoRechargeAction $action,
    ): RedirectResponse {
        $action->execute($request->toData());

        return back();
    }
}
