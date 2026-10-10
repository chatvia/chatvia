<?php

declare(strict_types=1);

namespace App\Admin\Resources\BillingPlans\Pages;

use App\Admin\Billing\StripeCatalogButtons;
use App\Admin\Resources\BillingPlans\BillingPlanResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

/**
 * The admin panel's list of plans, with "New plan", "Check against Stripe"
 * and "Sync with Stripe".
 */
class ListBillingPlans extends ListRecords
{
    #[\Override]
    protected static string $resource = BillingPlanResource::class;

    protected function getHeaderActions(): array
    {
        return [
            StripeCatalogButtons::check(),
            StripeCatalogButtons::sync(),
            CreateAction::make()->label('New plan'),
        ];
    }
}
