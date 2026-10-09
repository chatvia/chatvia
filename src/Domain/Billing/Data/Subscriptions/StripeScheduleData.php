<?php

namespace Domain\Billing\Data\Subscriptions;

use Carbon\CarbonInterface;
use Domain\Billing\Data\Stripe\StripeMetadata;
use Spatie\LaravelData\Data;

/**
 * The scheduled-plan-change block of a plan subscription's Stripe metadata.
 * While a change is pending, `pending_change` is 'true' and the other keys say
 * what changes and when; the renewal webhook and the billing page read them
 * back. A null value removes the key from the Stripe metadata.
 *
 * The named constructors and helpers below are the only writers of these keys,
 * so the spelling of each key (Stripe's `canceled`, one "l") stays consistent.
 */
class StripeScheduleData extends Data
{
    public function __construct(
        public ?string $schedule_id,
        public ?string $pending_change,
        public ?string $from_package,
        public ?string $to_package,
        public ?string $new_price_id,
        public ?string $is_yearly,
        public ?string $effective_date,
        public ?string $requested_at,
        public ?string $canceled_at,
    ) {}

    /** A plan change that takes effect at the end of the current period. */
    public static function pending(
        string $scheduleId,
        ?string $fromPackage,
        string $toPackage,
        string $newPriceId,
        bool $isYearly,
        CarbonInterface $effectiveDate,
    ): self {
        return new self(
            schedule_id: $scheduleId,
            pending_change: StripeMetadata::TRUE,
            from_package: $fromPackage,
            to_package: $toPackage,
            new_price_id: $newPriceId,
            is_yearly: StripeMetadata::flag($isYearly),
            effective_date: $effectiveDate->toISOString(),
            requested_at: now()->toISOString(),
            canceled_at: null,
        );
    }

    /** The customer withdrew the scheduled change before it took effect. */
    public static function withdrawn(): self
    {
        return new self(
            schedule_id: null,
            pending_change: StripeMetadata::FALSE,
            from_package: null,
            to_package: null,
            new_price_id: null,
            is_yearly: null,
            effective_date: null,
            requested_at: '',
            canceled_at: now()->toISOString(),
        );
    }

    /**
     * Metadata written once a renewal invoice has applied the scheduled change.
     *
     * @return array<string, string|null>
     */
    public static function appliedMetadata(): array
    {
        return [
            StripeMetadata::PENDING_CHANGE => StripeMetadata::FALSE,
            'applied_at' => now()->toISOString(),
            StripeMetadata::FROM_PACKAGE => null,
            StripeMetadata::TO_PACKAGE => null,
            'new_price_id' => null,
            StripeMetadata::IS_YEARLY => null,
            'scheduled_at' => null,
            'effective_date' => null,
        ];
    }

    /**
     * Metadata written when the whole subscription is cancelled while a change
     * was still pending.
     *
     * @return array<string, string|null>
     */
    public static function subscriptionCanceledMetadata(): array
    {
        return [
            StripeMetadata::PENDING_CHANGE => StripeMetadata::FALSE,
            StripeMetadata::FROM_PACKAGE => null,
            StripeMetadata::TO_PACKAGE => null,
            'new_price_id' => null,
            'scheduled_at' => null,
            'canceled_at' => now()->toISOString(),
            'cancellation_reason' => 'subscription_cancelled',
        ];
    }
}
