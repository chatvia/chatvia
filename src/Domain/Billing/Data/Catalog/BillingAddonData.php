<?php

declare(strict_types=1);

namespace Domain\Billing\Data\Catalog;

use Domain\Billing\Actions\Catalog\SaveBillingAddonAction;
use Spatie\LaravelData\Data;

/**
 * An add-on as the admin panel saves it ({@see SaveBillingAddonAction}):
 * texts, whether it is sold, and its price in the smallest currency unit per
 * `billingUnit`. Its key and kind are fixed in code, so they are not part of
 * it.
 */
class BillingAddonData extends Data
{
    public function __construct(
        public string $name,
        public ?string $description,
        public bool $isActive,
        public int $priceCents,
        public string $currency,
        public ?string $billingUnit,
        public ?string $icon,
    ) {}
}
