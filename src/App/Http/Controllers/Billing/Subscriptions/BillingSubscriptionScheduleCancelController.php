<?php

namespace App\Http\Controllers\Billing\Subscriptions;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Subscriptions\CancelScheduledPlanChangeAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Withdraws a scheduled plan change before it takes effect.
 */
class BillingSubscriptionScheduleCancelController
{
    public function __invoke(Request $request, CancelScheduledPlanChangeAction $cancelSchedule): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $cancelSchedule->execute($user)) {
            return to_route('billing.index')->with('error', __('No pending plan change found to cancel.'));
        }

        return to_route('billing.index')->with('success', __('Your scheduled plan change has been cancelled successfully.'));
    }
}
