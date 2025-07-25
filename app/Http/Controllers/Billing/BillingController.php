<?php

namespace App\Http\Controllers\Billing;

use App\Data\StripeScheduleData;
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
        $pendingPlanChange = null;

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

                // Check for pending plan changes
                $pendingPlanChange = $this->detectPendingPlanChange($stripeSubscription);

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
            'pending_plan_change' => $pendingPlanChange,
        ]);
    }

    private function detectPendingPlanChange($stripeSubscription)
    {
        try {
            // Check metadata for pending changes
            if (isset($stripeSubscription->metadata->pending_change) &&
                $stripeSubscription->metadata->pending_change === 'true') {

                $metadata = $stripeSubscription->metadata;

                return StripeScheduleData::from([
                    'pending_change' => $metadata->pending_change,
                    'from_package' => $metadata->from_package ?? null,
                    'to_package' => $metadata->to_package ?? null,
                    'is_yearly' => ($metadata->is_yearly ?? 'false') === 'true',
                    'effective_date' => $stripeSubscription->current_period_end, // Use next billing date
                    'requested_at' => $metadata->scheduled_at ?? null,
                ]);
            }

            // Also check for pending updates on subscription items
            foreach ($stripeSubscription->items->data as $item) {
                if (isset($item->pending_update) && $item->pending_update) {
                    // There's a pending update - get the new price info
                    return [
                        'from_package' => null, // We'd need to map current price to package
                        'to_package' => null,   // We'd need to map pending price to package
                        'effective_date' => $stripeSubscription->current_period_end * 1000,
                        'has_pending_update' => true,
                    ];
                }
            }

        } catch (Exception $e) {
            \Log::warning('Failed to detect pending plan changes', [
                'error' => $e->getMessage(),
                'subscription_id' => $stripeSubscription->id
            ]);
        }

        return null;
    }
}
