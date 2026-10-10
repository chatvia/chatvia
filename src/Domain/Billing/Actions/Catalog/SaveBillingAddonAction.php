<?php

declare(strict_types=1);

namespace Domain\Billing\Actions\Catalog;

use Domain\Billing\Data\Catalog\BillingAddonData;
use Domain\Billing\Data\Catalog\SavedBillingAddonData;
use Domain\Billing\Exceptions\InvalidBillingCatalogException;
use Domain\Billing\Models\BillingAddon;
use Domain\Billing\Services\Catalog\StripeCatalogSync;

/**
 * Saves an add-on's texts, price and sale status from the admin panel, then
 * sends it to Stripe ({@see StripeCatalogSync}): its product, and a new price
 * when the amount changed. Subscriptions on the old price keep it; the old id
 * stays on the add-on as a legacy price. Add-ons are only edited: their kinds
 * are fixed in code.
 *
 * @throws InvalidBillingCatalogException when the price is negative
 */
class SaveBillingAddonAction
{
    public function __construct(
        private readonly StripeCatalogSync $stripe,
    ) {}

    public function execute(BillingAddonData $data, BillingAddon $addon): SavedBillingAddonData
    {
        if ($data->priceCents < 0) {
            throw InvalidBillingCatalogException::negativePrice();
        }

        $addon->update([
            'name' => $data->name,
            'description' => $data->description,
            'is_active' => $data->isActive,
            'price_cents' => $data->priceCents,
            'currency' => strtolower($data->currency),
            'billing_unit' => $data->billingUnit,
            'icon' => $data->icon,
        ]);

        return new SavedBillingAddonData(addon: $addon, stripe: $this->stripe->addon($addon));
    }
}
