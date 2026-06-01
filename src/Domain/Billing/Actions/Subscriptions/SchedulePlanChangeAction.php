<?php

namespace Domain\Billing\Actions\Subscriptions;

use App\Billing\Data\StripeScheduleData;
use App\Billing\Services\Billing;
use Domain\Billing\Data\SchedulePlanChangeData;
use Laravel\Cashier\Cashier;

class SchedulePlanChangeAction
{
    public function __construct(
        private readonly Billing $billing,
    ) {}

    public function execute(SchedulePlanChangeData $data): bool
    {
        $user = $data->user;
        $userBilling = $user->accountBilling();

        if (! $userBilling?->subscribed()) {
            return false;
        }

        $toPackage = $this->billing->getPackageByName($data->package);
        $newPriceId = $data->isYearly ? $toPackage['stripe_yearly_price_id'] : $toPackage['stripe_monthly_price_id'];

        $subscription = $userBilling->subscription();

        if (! $subscription) {
            return false;
        }

        $stripeSubscription = $subscription->asStripeSubscription();
        $subscriptionItem = $stripeSubscription->items->data[0];
        $currentPeriodEnd = now()->timestamp($subscriptionItem->current_period_end);
        $metadata = $stripeSubscription->metadata->toArray();

        if (! isset($metadata['pending_change']) || $metadata['pending_change'] === 'true') {
            if (isset($metadata['schedule_id'])) {
                Cashier::stripe()->subscriptionSchedules->retrieve($metadata['schedule_id'])->release();
            }
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
            'metadata' => StripeScheduleData::from([
                'pending_change' => 'true',
                'from_package' => $user->plan,
                'to_package' => $data->package,
                'new_price_id' => $newPriceId,
                'is_yearly' => $data->isYearly ? 'true' : 'false',
                'effective_date' => $currentPeriodEnd->toISOString(),
                'requested_at' => now()->toISOString(),
                'schedule_id' => $schedule->id,
            ])->toArray(),
        ]);

        return true;
    }
}
