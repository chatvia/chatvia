<?php

namespace App\Http\Controllers\Billing\Home;

use App\Http\Requests\Billing\UpdateBillingInformationRequest;
use Domain\Billing\Actions\Account\UpdateBillingInformationAction;
use Exception;
use Illuminate\Http\RedirectResponse;

class BillingUpdateInformationController
{
    public function __invoke(
        UpdateBillingInformationRequest $request,
        UpdateBillingInformationAction $update,
    ): RedirectResponse {
        try {
            $update->execute($request->toData());
        } catch (Exception) {
            return back()->withErrors(['billing' => 'Failed to update billing information']);
        }

        return redirect()->route('billing.payment-methods')->with('success', 'Billing information updated successfully');
    }
}
