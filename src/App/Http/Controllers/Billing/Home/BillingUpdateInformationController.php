<?php

namespace App\Http\Controllers\Billing\Home;

use App\Http\Requests\Billing\UpdateBillingInformationRequest;
use Domain\Billing\Actions\Account\UpdateBillingInformationAction;
use Exception;
use Illuminate\Http\RedirectResponse;

/**
 * Saves the workspace's billing name, email and address (locally and on the
 * Stripe customer).
 */
class BillingUpdateInformationController
{
    public function __invoke(
        UpdateBillingInformationRequest $request,
        UpdateBillingInformationAction $update,
    ): RedirectResponse {
        try {
            $update->execute($request->toData());
        } catch (Exception) {
            return back()->withErrors(['billing' => __('Failed to update billing information')]);
        }

        return to_route('billing.payment-methods')->with('success', __('Billing information updated successfully'));
    }
}
