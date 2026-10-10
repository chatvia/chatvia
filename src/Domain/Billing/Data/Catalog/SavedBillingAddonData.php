<?php

declare(strict_types=1);

namespace Domain\Billing\Data\Catalog;

use Domain\Billing\Models\BillingAddon;
use Spatie\LaravelData\Data;

/**
 * An add-on that was just saved, and what sending it to Stripe did (or why
 * it was not sent).
 */
class SavedBillingAddonData extends Data
{
    public function __construct(
        public BillingAddon $addon,
        public StripeCatalogSyncData $stripe,
    ) {}
}
