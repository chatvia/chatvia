<?php

declare(strict_types=1);

namespace App\Admin\Billing;

use Domain\Billing\Data\Catalog\StripeCatalogSyncData;
use Domain\Billing\Data\Catalog\StripePriceChangeData;
use Domain\Billing\Enums\StripeSyncBlocker;
use Filament\Notifications\Notification;
use Illuminate\Support\Number;
use Illuminate\Support\Str;

/**
 * The notifications the admin panel shows after a plan or add-on is saved or
 * synced, or the catalog is checked against Stripe: what changed (or would
 * change) in Stripe, why nothing was sent, or the error Stripe returned.
 * Every new price is listed with the amount it replaces, and a replaced price
 * comes with the reminder that existing subscribers keep their old price.
 */
final class StripeSyncNotification
{
    /**
     * @param  string|null  $saved  What was saved first, e.g. "Plan saved"; null for a sync on its own.
     */
    public static function make(StripeCatalogSyncData $result, ?string $saved = null): Notification
    {
        if ($result->blocker instanceof StripeSyncBlocker) {
            return self::blocked($result->blocker, ($saved !== null ? "{$saved}. " : '').'Not sent to Stripe: ');
        }

        if ($result->failed()) {
            return Notification::make()
                ->danger()
                ->title($saved !== null ? "{$saved}, but Stripe returned an error" : 'Stripe returned an error')
                ->body(self::lines([...self::changes($result), $result->failure, 'Fix the problem, then save again or press "Sync with Stripe".']))
                ->persistent();
        }

        $notification = Notification::make()
            ->success()
            ->title($saved !== null ? "{$saved} and synced with Stripe" : 'Synced with Stripe')
            ->body($result->changedStripe() ? self::lines(self::changes($result)) : 'Stripe already had these products and prices: nothing was changed.');

        // A price change stays on screen until it is read.
        return $result->replacedPrices() ? $notification->persistent() : $notification;
    }

    /** The result of "Check against Stripe", which changes nothing. */
    public static function preview(StripeCatalogSyncData $result): Notification
    {
        if ($result->blocker instanceof StripeSyncBlocker) {
            return self::blocked($result->blocker, 'Stripe was not checked: ');
        }

        if ($result->failed()) {
            return Notification::make()->danger()->title('Stripe returned an error')->body($result->failure)->persistent();
        }

        if ($result->createdPriceIds === [] && $result->retiredPriceIds === []) {
            return Notification::make()
                ->success()
                ->title('Every price in Stripe matches')
                ->body(self::lines([
                    'Sync with Stripe creates no price.',
                    self::count('It would create', count($result->createdProductIds), 'product'),
                    self::count('It would rename', count($result->updatedProductIds), 'product'),
                ]))
                ->persistent();
        }

        return Notification::make()
            ->warning()
            ->title('Sync with Stripe would change prices')
            ->body(self::lines([
                ...array_map(self::priceChange(...), $result->priceChanges),
                self::count('Stops selling', self::retiredWithoutReplacement($result), 'price', suffix: ' without a new one.'),
                'New customers would pay the new prices; existing subscribers keep theirs. To keep a price, set the amount saved here to the amount in Stripe.',
            ]))
            ->persistent();
    }

    private static function blocked(StripeSyncBlocker $blocker, string $prefix): Notification
    {
        return Notification::make()
            ->warning()
            ->title($prefix.$blocker->reason())
            ->body('Stripe is used once paid plans are on and a Stripe secret key is saved.')
            ->persistent();
    }

    /**
     * One plain line per change, for example "Created 2 prices in Stripe."
     *
     * @return list<string|null>
     */
    private static function changes(StripeCatalogSyncData $result): array
    {
        return [
            self::count('Created', count($result->createdProductIds), 'product'),
            self::count('Updated', count($result->updatedProductIds), 'product'),
            ...array_map(self::priceChange(...), $result->priceChanges),
            $result->replacedPrices()
                ? 'Existing subscribers keep their old price; new customers pay the new one.'
                : null,
        ];
    }

    /** "Starter (monthly): EUR 99.00 in Stripe, new price EUR 49.00." */
    private static function priceChange(StripePriceChangeData $change): string
    {
        $new = self::money($change->newAmount, $change->newCurrency);

        return $change->oldAmount !== null && $change->oldCurrency !== null
            ? "{$change->item} ({$change->slot}): ".self::money($change->oldAmount, $change->oldCurrency)." in Stripe, new price {$new}."
            : "{$change->item} ({$change->slot}): new price {$new}.";
    }

    private static function money(int $cents, string $currency): string
    {
        return (string) Number::currency($cents / 100, in: strtoupper($currency), locale: 'en');
    }

    private static function count(string $verb, int $count, string $noun, string $suffix = ' in Stripe.'): ?string
    {
        return $count > 0 ? "{$verb} {$count} ".Str::plural($noun, $count).$suffix : null;
    }

    /** Prices a plan or add-on stops selling with and does not replace (a cycle or add-on taken off sale). */
    private static function retiredWithoutReplacement(StripeCatalogSyncData $result): int
    {
        $replaced = array_filter($result->priceChanges, fn (StripePriceChangeData $change): bool => $change->oldPriceId !== null);

        return count($result->retiredPriceIds) - count($replaced);
    }

    /**
     * @param  list<string|null>  $lines
     */
    private static function lines(array $lines): string
    {
        return implode(' ', array_filter($lines, filled(...)));
    }
}
