<?php

namespace App\Http\Controllers\Billing\Subscriptions;

use App\Data\StripeScheduleData;
use App\Models\User;
use App\Support\Billing;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Stripe\Stripe;
use Stripe\Subscription;
use Stripe\SubscriptionSchedule;

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
            return to_route('billing.index')->with('error', 'You cannot downgrade to the free plan. Please cancel your subscription from the billing page if you want to stop your subscription at the end of your billing cycle.');
        }

        $toPackage = $billing->getPackageByName(
            $request->input('package')
        );

        $currentPackageName = strtolower($toPackage['name']);

        $isYearly = (bool) $request->input('is_yearly');

        $newPriceId = $isYearly === true ? $toPackage['stripe_yearly_price_id'] : $toPackage['stripe_monthly_price_id'];

        $subscription = $user->subscription();

        if (!$subscription) {
            return to_route('billing.index')->with('error', 'There is an issue with your subscription. Please try again later.');
        }

        $stripeSubscription = $subscription->asStripeSubscription();

        $currentPeriodEnd = now()->timestamp($stripeSubscription->current_period_end);

        $metadata = $stripeSubscription->metadata->toArray();

        if (!isset($metadata['pending_change']) || $metadata['pending_change'] === 'true') {
            $schedule = SubscriptionSchedule::retrieve($metadata['schedule_id']);
            $schedule->release();
        }

        // Create a subscription schedule to change the plan at the end of current period
        $schedule = SubscriptionSchedule::create([
            'from_subscription' => $stripeSubscription->id,
        ]);

        // Now update the schedule with the new phases
        $schedule = SubscriptionSchedule::update($schedule->id, [
            'phases' => [
                [
                    'items' => [
                        [
                            'price' => $stripeSubscription->items->data[0]->price->id,
                            'quantity' => 1,
                        ],
                    ],
                    'start_date' => $stripeSubscription->current_period_start,
                    'end_date' => $stripeSubscription->current_period_end,
                ],
                [
                    'items' => [
                        [
                            'price' => $newPriceId,
                            'quantity' => 1,
                        ],
                    ],
                    'start_date' => $stripeSubscription->current_period_end,
                ],
            ],
        ]);

        Subscription::update($stripeSubscription->id, [
            'metadata' => StripeScheduleData::from([
                'pending_change' => 'true',
                'from_package' => $user->plan,
                'to_package' => $requestedPackage,
                'new_price_id' => $newPriceId,
                'is_yearly' => $isYearly ? 'true' : 'false',
                'effective_date' => $currentPeriodEnd->toISOString(),
                'requested_at' => now()->toISOString(),
                'schedule_id' => $schedule->id,
            ])->toArray(),
        ]);

        return to_route('billing.index')->with('success', 'Your subscription plan has been updated successfully!');
    }
}
