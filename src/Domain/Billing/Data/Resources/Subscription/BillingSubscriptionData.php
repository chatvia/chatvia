<?php

namespace Domain\Billing\Data\Resources\Subscription;

use Laravel\Cashier\Subscription;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

#[TypeScript]
class BillingSubscriptionData extends Data
{
    public function __construct(
        public int $id,
        public string $stripe_id,
        public string $stripe_status,
        public ?string $stripe_price,
        public ?int $trial_ends_at,
        public ?int $ends_at,
        public int $current_period_end,
        public int $current_period_start,
    ) {}

    public static function fromSubscription(Subscription $subscription, object $stripeSubscription): self
    {
        return new self(
            id: $subscription->id,
            stripe_id: $subscription->stripe_id,
            stripe_status: $subscription->stripe_status,
            stripe_price: $subscription->stripe_price,
            trial_ends_at: $subscription->trial_ends_at?->getTimestamp() * 1000,
            ends_at: $subscription->ends_at?->getTimestamp() * 1000,
            current_period_end: $stripeSubscription->current_period_end * 1000,
            current_period_start: $stripeSubscription->current_period_start * 1000,
        );
    }
}
