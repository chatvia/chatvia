<?php

namespace App\Http\Controllers\Billing;

use Exception;
use Illuminate\Http\Request;
use Inertia\Inertia;

class BillingController
{
    public function __invoke(Request $request)
    {
        $user = $request->user();

        $currentPackage = $user->getCurrentPackage();

        // Get subscription with next billing date
        $subscription = $user->subscription();
        $subscriptionData = null;

        if ($subscription && $subscription->stripe_id) {
            try {
                // Get the subscription from Stripe to get current_period_end
                $stripeSubscription = $subscription->asStripeSubscription();
                $subscriptionData = [
                    'id' => $subscription->id,
                    'stripe_id' => $subscription->stripe_id,
                    'stripe_status' => $subscription->stripe_status,
                    'stripe_price' => $subscription->stripe_price,
                    'trial_ends_at' => $subscription->trial_ends_at,
                    'ends_at' => $subscription->ends_at ? $subscription->ends_at->getTimestamp() * 1000 : null,
                    'current_period_end' => $stripeSubscription->current_period_end * 1000, // Convert to milliseconds for JS
                    'current_period_start' => $stripeSubscription->current_period_start * 1000,
                ];
            } catch (Exception $e) {
                // Fallback to basic subscription data if Stripe API fails
                $subscriptionData = $subscription;
            }
        }

        return Inertia::render('billing/Index', [
            'current_package' => $currentPackage,
            'on_trial' => $user->onTrial(),
            'subscription_canceled' => $user->subscription()?->canceled(),
            'trial_ends_at' => $user->trialEndsAt()?->toFormattedDateString(),
            'subscription' => $subscriptionData,
            'subscribed' => $user->subscribed(),
        ]);
    }
}
