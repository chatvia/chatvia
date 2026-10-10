<?php

declare(strict_types=1);

namespace Domain\Billing\Data\Catalog;

use Spatie\LaravelData\Data;

/**
 * One new Stripe price a sync created (or, in a preview, would create): which
 * plan or add-on and which way it is sold (`slot`), what the price in Stripe
 * charged before (null when there was none, or Stripe did not know it), and
 * what the new price charges. Amounts are in the smallest currency unit.
 */
class StripePriceChangeData extends Data
{
    public function __construct(
        public string $item,
        public string $slot,
        public ?string $oldPriceId,
        public ?int $oldAmount,
        public ?string $oldCurrency,
        public int $newAmount,
        public string $newCurrency,
    ) {}
}
