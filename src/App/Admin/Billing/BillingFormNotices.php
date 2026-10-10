<?php

declare(strict_types=1);

namespace App\Admin\Billing;

use Closure;
use Domain\Billing\Enums\StripeSyncBlocker;
use Domain\Billing\Services\Catalog\StripeCatalogSync;
use Filament\Schemas\Components\Callout;
use Filament\Support\Icons\Heroicon;
use Illuminate\Database\Eloquent\Model;

/**
 * Warnings shown at the top of the plan and add-on forms, before anything is
 * saved: that a save will not reach Stripe (paid plans off, no secret key),
 * and that a save will reprice an item whose currency is not the billing
 * currency.
 */
final class BillingFormNotices
{
    public static function notSentToStripe(): Callout
    {
        $blocker = app(StripeCatalogSync::class)->blocker();

        return Callout::make('Saved here only')
            ->description(fn (): string => 'Saving does not send anything to Stripe: '.$blocker?->reason())
            ->icon(Heroicon::OutlinedInformationCircle)
            ->info()
            ->visible($blocker instanceof StripeSyncBlocker);
    }

    /**
     * @param  Closure(Model|null): (string|null)  $currencyOf  The saved currency of the record, null on create.
     */
    public static function currencyChange(Closure $currencyOf): Callout
    {
        return Callout::make('The currency changes on save')
            ->description(fn (?Model $record): string => sprintf(
                'This is priced in %s, but the billing currency is %s. Saving it creates new Stripe prices in %2$s.',
                strtoupper((string) $currencyOf($record)),
                BillingAmount::currencyCode(),
            ))
            ->icon(Heroicon::OutlinedExclamationTriangle)
            ->warning()
            ->visible(function (?Model $record) use ($currencyOf): bool {
                $currency = $currencyOf($record);

                return $currency !== null && strtolower($currency) !== BillingAmount::currency();
            });
    }
}
