<?php

namespace App\Http\Controllers\Billing;

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

        $packages = config('billing.packages');

        if (! isset($packages[$package])) {
            return to_route('dashboard');
        }

        $stripePriceId = $yearly ? $packages[$package]['stripe_monthly_price_id'] : $packages[$package]['stripe_yearly_price_id'];

        return $request
            ->user()
            ->newSubscription(type: 'default', prices: $stripePriceId)
            ->withMetadata([
                'package' => $package,
                'is_yearly' => $yearly,
                'from_package' => '',
                'to_package' => '',
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
