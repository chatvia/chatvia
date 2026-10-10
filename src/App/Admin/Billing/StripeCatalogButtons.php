<?php

declare(strict_types=1);

namespace App\Admin\Billing;

use Domain\Billing\Actions\Catalog\PreviewBillingCatalogSyncAction;
use Domain\Billing\Actions\Catalog\SyncBillingCatalogWithStripeAction;
use Filament\Actions\Action;
use Filament\Support\Icons\Heroicon;

/**
 * The Stripe buttons of the plan and add-on lists. "Check against Stripe"
 * only reads, and lists every saved amount that differs from the live Stripe
 * price; "Sync with Stripe" sends every plan and add-on, and its confirmation
 * says up front that only changed amounts make new prices.
 */
final class StripeCatalogButtons
{
    public static function check(): Action
    {
        return Action::make('checkAgainstStripe')
            ->label('Check against Stripe')
            ->icon(Heroicon::OutlinedMagnifyingGlass)
            ->color('gray')
            ->action(function (): void {
                StripeSyncNotification::preview(app(PreviewBillingCatalogSyncAction::class)->execute())->send();
            });
    }

    public static function sync(): Action
    {
        return Action::make('syncWithStripe')
            ->label('Sync with Stripe')
            ->icon(Heroicon::OutlinedArrowPath)
            ->color('gray')
            ->requiresConfirmation()
            ->modalIcon(Heroicon::OutlinedArrowPath)
            ->modalHeading('Sync every plan and add-on with Stripe?')
            ->modalDescription('Each plan and add-on gets a Stripe product with its name. A new Stripe price is created only where the amount saved here differs from the price in Stripe; existing subscribers keep the price they pay. Nothing in Stripe is deleted or archived. Press "Check against Stripe" first to see which prices would change.')
            ->modalSubmitActionLabel('Sync')
            ->action(function (): void {
                StripeSyncNotification::make(app(SyncBillingCatalogWithStripeAction::class)->execute())->send();
            });
    }
}
