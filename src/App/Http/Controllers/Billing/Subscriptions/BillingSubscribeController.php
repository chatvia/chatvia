<?php

namespace App\Http\Controllers\Billing\Subscriptions;

use App\Auth\Models\User;
use Illuminate\Http\Request;
use Laravel\Cashier\SubscriptionBuilder;

class BillingSubscribeController
{
    public function __invoke(Request $request)
    {
        $request->validate([
            'package' => ['required', 'string'],
            'yearly' => ['required', 'boolean'],
        ]);

        $package = $request->input('package');
        $yearly = $request->input('yearly');

        if (! $package) {
            return to_route('dashboard');
        }

        $plans = config('billing.packages');

        if (! isset($plans[$package])) {
            return to_route('dashboard');
        }

        /** @var User $user */
        $user = $request->user();

        $stripePriceId = $yearly ?
            $plans[$package]['stripe_yearly_price_id'] :
            $plans[$package]['stripe_monthly_price_id'];

        return $request
            ->user()
            ->newSubscription(type: 'default', prices: $stripePriceId)
            ->withMetadata([
                'id' => strtolower($package),
                'type' => 'subscription',
                'from_package' => $user->plan,
                'to_package' => strtolower($package),
                'is_yearly' => $yearly,
                'user_subscribed' => $user->subscribed(),
            ])
            ->when(config('billing.trial.days') !== 0, function (SubscriptionBuilder $subscription) {
                $subscription->trialDays((int) config('billing.trial.days'));
            })
            ->allowPromotionCodes()
            ->alwaysInvoice()
            ->checkout([
                'success_url' => route('billing.success'),
                'cancel_url' => route('billing.index'),
            ]);
    }

    public function higherPackage($fromPackage, $toPackage)
    {
        if ($fromPackage === $toPackage) {
            return false;
        }

        // Define package hierarchy based on your billing config
        $packageHierarchy = [
            'starter' => 1,
            'standard' => 2,
            'pro' => 3,
        ];

        $fromLevel = $packageHierarchy[$fromPackage] ?? 0;
        $toLevel = $packageHierarchy[$toPackage] ?? 0;

        return $toLevel > $fromLevel;
    }
}
