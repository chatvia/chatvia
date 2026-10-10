<?php

declare(strict_types=1);

namespace Domain\Billing\Enums;

/**
 * How often a plan price bills. The value matches the keys of a plan's
 * `quota_limits` (`monthly` / `yearly`) and the values of its
 * `legacy_stripe_price_ids` map, so values must never change.
 */
enum BillingInterval: string
{
    case Monthly = 'monthly';
    case Yearly = 'yearly';

    /** The interval of a checkout or plan change that posts a yearly yes/no flag. */
    public static function fromYearly(bool $yearly): self
    {
        return $yearly ? self::Yearly : self::Monthly;
    }

    public function isYearly(): bool
    {
        return $this === self::Yearly;
    }
}
