<?php

namespace App\Http\Controllers\Billing\Checkout;

use App\Models\User;
use Illuminate\Http\Request;
use Laravel\Cashier\SubscriptionBuilder;

class CheckoutController
{
    public function __invoke(Request $request)
    {
        $package = $request->input('package');
        $yearly = $request->input('yearly');

        if (! $package || $yearly === null) {
            return to_route('dashboard');
        }

        $plans = config('billing.packages');

        if (! isset($plans[$package])) {
            return to_route('dashboard');
        }

        /** @var User $user */
        $user = $request->user();

        $stripePriceId = $yearly ? $plans[$package]['stripe_monthly_price_id'] : $plans[$package]['stripe_yearly_price_id'];

        return $request
            ->user()
            ->newSubscription(type: 'default', prices: $stripePriceId)
            ->withMetadata([
                'from_package' => $user->plan,
                'to_package' => $package,
                'is_yearly' => $yearly,
            ])
            ->when(config('billing.trial.days') !== 0, function (SubscriptionBuilder $subscription) {
                $subscription->trialDays(config('billing.trial.days'));
            })
            ->allowPromotionCodes()
            ->alwaysInvoice()
            ->checkout([
                'success_url' => route('billing.index'),
                'cancel_url' => route('billing.index'),
            ]);
    }
}
