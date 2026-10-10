<?php

declare(strict_types=1);

namespace Domain\Billing\Data\Catalog;

/**
 * One Stripe price a plan or add-on should have: the amount in the smallest
 * currency unit, the currency, and how it bills (`month` / `year`, or null
 * for a one-time price). `amount` is null when the plan or add-on is not sold
 * this way (a contact-sales plan, a plan without a yearly price, a zero
 * amount). `currentPriceId` is the price it has now, if any.
 *
 * `slot` names the price within its plan or add-on (`monthly`, `yearly`,
 * `one_time`); it goes into the price's metadata and nickname.
 */
final readonly class StripePriceSpecData
{
    public const string RECURRING_MONTH = 'month';

    public const string RECURRING_YEAR = 'year';

    public function __construct(
        public StripeProductSpecData $product,
        public string $slot,
        public ?int $amount,
        public string $currency,
        public ?string $recurringInterval,
        public ?string $currentPriceId,
    ) {}

    public function isWanted(): bool
    {
        return $this->amount !== null && $this->amount > 0;
    }

    public function nickname(): string
    {
        return "{$this->product->name} ({$this->slot})";
    }
}
