<?php

declare(strict_types=1);

namespace App\Admin\Billing;

use Domain\Billing\Enums\BillingInterval;
use Domain\Billing\Models\BillingAddon;
use Domain\Billing\Models\BillingPlan;
use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Whether a plan or add-on is linked to Stripe, for the "Stripe" column of the
 * admin lists. Read from the stored ids only (no Stripe call): "Synced" means
 * it has a Stripe product and a price for every way it is sold. The amounts
 * themselves are checked against Stripe when it is saved or synced.
 */
enum StripeSyncState: string implements HasColor, HasLabel
{
    case Synced = 'synced';
    case NotSynced = 'not_synced';
    case NotSold = 'not_sold';

    public static function forPlan(BillingPlan $plan): self
    {
        if ($plan->contact_sales) {
            return self::NotSold;
        }

        $sold = array_filter(
            BillingInterval::cases(),
            fn (BillingInterval $interval): bool => ($interval->isYearly() ? $plan->yearly_price_cents : $plan->monthly_price_cents) > 0,
        );

        return self::state($sold !== [], $plan->stripe_product_id, array_map($plan->stripePriceId(...), $sold));
    }

    public static function forAddon(BillingAddon $addon): self
    {
        return self::state($addon->price_cents > 0, $addon->stripe_product_id, [$addon->stripe_price_id]);
    }

    public function getLabel(): string
    {
        return match ($this) {
            self::Synced => 'Synced',
            self::NotSynced => 'Not synced',
            self::NotSold => 'Not sold online',
        };
    }

    public function getColor(): string
    {
        return match ($this) {
            self::Synced => 'success',
            self::NotSynced => 'warning',
            self::NotSold => 'gray',
        };
    }

    /**
     * @param  array<array-key, string|null>  $priceIds
     */
    private static function state(bool $sold, ?string $productId, array $priceIds): self
    {
        if (! $sold) {
            return self::NotSold;
        }

        return filled($productId) && array_all($priceIds, filled(...)) ? self::Synced : self::NotSynced;
    }
}
