<?php

namespace App\Http\Controllers\Billing\Subscriptions;

use App\Models\User;
use App\Support\Billing;
use Illuminate\Http\Request;

class BillingSubscriptionChangeController
{
    public function __invoke(Request $request, Billing $billing)
    {
        $request->validate([
            'package' => ['required', 'string', 'in:free,starter,standard,pro'],
            'is_yearly' => ['required', 'boolean'],
        ]);

        /** @var User $user */
        $user = $request->user();

        $requestedPackage = $request->input('package');

        if (! $user->subscribed()) {
            return to_route('billing.index');
        }

        if ($requestedPackage === 'free') {
            $user->subscription()?->cancel();

            return to_route('billing.index');
        }

        $package = $billing->getPackageByName(
            ucfirst($request->input('package'))
        );

        $isYearly = (bool) $request->input('is_yearly');

        $priceId = $isYearly === 0 ? $package['stripe_monthly_price_id'] : $package['stripe_yearly_price_id'];

        $user->subscription()?->swap($priceId);
    }
}
