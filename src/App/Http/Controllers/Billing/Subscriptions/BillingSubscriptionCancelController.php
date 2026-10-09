<?php

namespace App\Http\Controllers\Billing\Subscriptions;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Subscriptions\CancelSubscriptionAction;
use Illuminate\Http\Request;

/**
 * Cancels the plan subscription at the end of the billing period.
 */
class BillingSubscriptionCancelController
{
    public function __invoke(Request $request, CancelSubscriptionAction $cancel): void
    {
        /** @var User $user */
        $user = $request->user();

        $cancel->execute($user);
    }
}
