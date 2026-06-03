<?php

namespace App\Http\Controllers\Billing\Subscriptions;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Subscriptions\CancelScheduledPlanChangeAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BillingSubscriptionScheduleCancelController
{
    public function __invoke(Request $request, CancelScheduledPlanChangeAction $cancelSchedule): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $cancelSchedule->execute($user)) {
            return to_route('billing.index')->with('error', 'No pending plan change found to cancel.');
        }

        return to_route('billing.index')->with('success', 'Your scheduled plan change has been cancelled successfully.');
    }
}
