<?php

declare(strict_types=1);

namespace App\Admin\Resources\BillingAddons\Pages;

use App\Admin\Billing\StripeCatalogButtons;
use App\Admin\Resources\BillingAddons\BillingAddonResource;
use Filament\Resources\Pages\ListRecords;

/**
 * The admin panel's list of add-ons, with "Check against Stripe" and "Sync
 * with Stripe".
 */
class ListBillingAddons extends ListRecords
{
    #[\Override]
    protected static string $resource = BillingAddonResource::class;

    protected function getHeaderActions(): array
    {
        return [
            StripeCatalogButtons::check(),
            StripeCatalogButtons::sync(),
        ];
    }
}
