<?php

declare(strict_types=1);

namespace Domain\Billing\Models;

use Database\Factories\BillingAddonFactory;
use Domain\Billing\Enums\Addon;
use Domain\Billing\Enums\MessageCreditAddon;
use Domain\Billing\Observers\BillingCatalogObserver;
use Domain\Billing\Services\Catalog\BillingCatalog;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * The price and texts of one add-on. The kinds of add-on are fixed in code
 * ({@see Addon} for subscriptions, {@see MessageCreditAddon} for credit
 * packs), so there is one row per enum value and `key` is that value; it
 * cannot change. Read it through {@see BillingCatalog}, which caches every
 * add-on.
 *
 * An inactive add-on is not sold: it has no purchasable price.
 * `legacy_stripe_price_ids` lists the add-on's older Stripe prices.
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property bool $is_active
 * @property int $price_cents
 * @property string $currency
 * @property string|null $billing_unit
 * @property string|null $icon
 * @property int|null $unit_messages
 * @property string|null $stripe_product_id
 * @property string|null $stripe_price_id
 * @property list<string> $legacy_stripe_price_ids
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static BillingAddonFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
#[ObservedBy(BillingCatalogObserver::class)]
class BillingAddon extends Model
{
    /** @use HasFactory<BillingAddonFactory> */
    use HasFactory;

    #[\Override]
    protected static function booted(): void
    {
        static::updating(function (self $addon): void {
            if ($addon->isDirty('key')) {
                throw new LogicException('A billing add-on key cannot change: it is the add-on enum value.');
            }
        });
    }

    #[\Override]
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'price_cents' => 'integer',
            'unit_messages' => 'integer',
            'legacy_stripe_price_ids' => 'array',
        ];
    }

    /** The Stripe price to sell the add-on with, or null when it is inactive or has none. */
    public function purchasablePriceId(): ?string
    {
        return $this->is_active && filled($this->stripe_price_id) ? $this->stripe_price_id : null;
    }

    /** Whether a Stripe price is the add-on's current price or one of its older ones. */
    public function billsPrice(string $priceId): bool
    {
        return $priceId !== ''
            && ($priceId === $this->stripe_price_id || in_array($priceId, $this->legacy_stripe_price_ids, true));
    }
}
