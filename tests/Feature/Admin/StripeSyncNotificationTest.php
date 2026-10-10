<?php

declare(strict_types=1);

use App\Admin\Billing\StripeSyncNotification;
use Domain\Billing\Data\Catalog\StripeCatalogSyncData;
use Domain\Billing\Data\Catalog\StripePriceChangeData;
use Domain\Billing\Enums\StripeSyncBlocker;

/**
 * The admin notifications after a save, a sync or a check: every new price is
 * listed with the amount it replaces, and a replaced price comes with the
 * reminder that subscribers keep their old price.
 */
function proPriceChange(): StripeCatalogSyncData
{
    return new StripeCatalogSyncData(
        createdPriceIds: ['price_new'],
        retiredPriceIds: ['price_old'],
        priceChanges: [new StripePriceChangeData(
            item: 'Pro',
            slot: 'monthly',
            oldPriceId: 'price_old',
            oldAmount: 49900,
            oldCurrency: 'eur',
            newAmount: 59900,
            newCurrency: 'eur',
        )],
    );
}

it('lists a new price with the old amount and says subscribers keep their price', function (): void {
    $notification = StripeSyncNotification::make(proPriceChange(), 'Plan saved');

    expect($notification->getTitle())->toBe('Plan saved and synced with Stripe')
        ->and($notification->getBody())->toBe('Pro (monthly): €499.00 in Stripe, new price €599.00. Existing subscribers keep their old price; new customers pay the new one.')
        ->and($notification->getDuration())->toBe('persistent');
});

it('says why nothing was sent', function (): void {
    $notification = StripeSyncNotification::make(StripeCatalogSyncData::blocked(StripeSyncBlocker::NoSecretKey), 'Add-on saved');

    expect($notification->getTitle())->toBe('Add-on saved. Not sent to Stripe: no Stripe secret key is saved (Settings → Stripe).');
});

it('shows Stripe\'s error after the changes made before it', function (): void {
    $notification = StripeSyncNotification::make(new StripeCatalogSyncData(failure: 'Could not connect to Stripe.', createdProductIds: ['prod_1']));

    expect($notification->getTitle())->toBe('Stripe returned an error')
        ->and($notification->getBody())->toBe('Created 1 product in Stripe. Could not connect to Stripe. Fix the problem, then save again or press "Sync with Stripe".');
});

it('reports the prices a sync would change, and prices it would stop selling', function (): void {
    $result = proPriceChange()->merge(new StripeCatalogSyncData(retiredPriceIds: ['price_yearly_gone']));

    $notification = StripeSyncNotification::preview($result);

    expect($notification->getTitle())->toBe('Sync with Stripe would change prices')
        ->and($notification->getBody())->toContain('Pro (monthly): €499.00 in Stripe, new price €599.00.')
        ->and($notification->getBody())->toContain('Stops selling 1 price without a new one.');
});
