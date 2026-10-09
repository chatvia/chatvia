<?php

namespace Domain\Billing\Actions\Subscriptions;

use Domain\Billing\Data\Stripe\StripeMetadata;
use Domain\Billing\Data\Subscriptions\SchedulePlanChangeData;
use Domain\Billing\Data\Subscriptions\StripeScheduleData;
use Domain\Billing\Services\PlanCatalog;
use Laravel\Cashier\Cashier;

/**
 * Schedules a move to another plan at the end of the current billing period,
 * through a Stripe subscription schedule with two phases (the current price
 * until the period ends, then the new price). The pending change is recorded
 * in the subscription's metadata ({@see StripeScheduleData}) so the billing
 * page can show it and the renewal webhook can clear it.
 *
 * Returns false when the account has no active plan subscription.
 */
class SchedulePlanChangeAction
{
    public function __construct(
        private readonly PlanCatalog $plans,
    ) {}

    public function execute(SchedulePlanChangeData $data): bool
    {
        $user = $data->user;
        $userBilling = $user->accountBilling();
        $subscription = $userBilling?->subscribed() ? $userBilling->ownedSubscription('default') : null;

        if ($subscription === null) {
            return false;
        }

        $toPackage = $this->plans->packageByName($data->package);
        $newPriceId = $data->isYearly ? $toPackage['stripe_yearly_price_id'] : $toPackage['stripe_monthly_price_id'];

        $stripeSubscription = $subscription->asStripeSubscription();
        $subscriptionItem = $stripeSubscription->items->data[0];
        $metadata = StripeMetadata::from($stripeSubscription->metadata->toArray());

        // A change that is still pending (or metadata that never recorded one)
        // may hold a schedule: release it before creating the new one.
        if ((! $metadata->has(StripeMetadata::PENDING_CHANGE) || $metadata->hasPendingChange()) && $metadata->scheduleId() !== null) {
            Cashier::stripe()->subscriptionSchedules->retrieve($metadata->scheduleId())->release();
        }

        $schedule = Cashier::stripe()->subscriptionSchedules->create([
            'from_subscription' => $stripeSubscription->id,
        ]);

        Cashier::stripe()->subscriptionSchedules->update($schedule->id, [
            'phases' => [
                [
                    'items' => [['price' => $subscriptionItem->price->id, 'quantity' => 1]],
                    'start_date' => $subscriptionItem->current_period_start,
                    'end_date' => $subscriptionItem->current_period_end,
                ],
                [
                    'items' => [['price' => $newPriceId, 'quantity' => 1]],
                    'start_date' => $subscriptionItem->current_period_end,
                ],
            ],
        ]);

        Cashier::stripe()->subscriptions->update($stripeSubscription->id, [
            'metadata' => StripeScheduleData::pending(
                scheduleId: $schedule->id,
                fromPackage: $user->plan,
                toPackage: $data->package,
                newPriceId: $newPriceId,
                isYearly: $data->isYearly,
                effectiveDate: now()->timestamp($subscriptionItem->current_period_end),
            )->toArray(),
        ]);

        return true;
    }
}
