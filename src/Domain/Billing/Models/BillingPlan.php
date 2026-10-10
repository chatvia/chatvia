<?php

declare(strict_types=1);

namespace Domain\Billing\Models;

use Database\Factories\BillingPlanFactory;
use Domain\Billing\Data\Resources\Package\BillingPackageCapabilitiesData;
use Domain\Billing\Enums\BillingInterval;
use Domain\Billing\Observers\BillingCatalogObserver;
use Domain\Billing\Services\Catalog\BillingCatalog;
use Illuminate\Database\Eloquent\Attributes\Guarded;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use LogicException;

/**
 * A plan a workspace can subscribe to: its prices, Stripe price ids, feature
 * flags (`capabilities`) and limits (`quota_limits`). Read it through
 * {@see BillingCatalog}, which caches every plan.
 *
 * `key` is the plan id used everywhere (Stripe metadata, `user_billing.plan`,
 * the frontend), so it cannot change once the plan exists. An archived plan
 * (`is_active` false) is not offered any more but still resolves for the
 * workspaces subscribed to it. A Stripe price matches the plan when it is one
 * of the current prices or one of `legacy_stripe_price_ids` (price id =>
 * interval), so renewals of subscriptions on an older price keep working.
 *
 * `quota_limits` holds `monthly` and `yearly` (`messages_limit`) and `fixed`
 * (the `user_quotas` columns: `agents_limit`, `teams_limit`, ...).
 *
 * @property int $id
 * @property string $key
 * @property string $name
 * @property string|null $description
 * @property string|null $icon
 * @property int $sort_order
 * @property bool $is_active
 * @property bool $is_default
 * @property bool $is_popular
 * @property bool $contact_sales
 * @property int $monthly_price_cents
 * @property int|null $yearly_price_cents
 * @property string $currency
 * @property string|null $stripe_product_id
 * @property string|null $stripe_monthly_price_id
 * @property string|null $stripe_yearly_price_id
 * @property array<string, string> $legacy_stripe_price_ids
 * @property BillingPackageCapabilitiesData $capabilities
 * @property array<string, array<string, int>> $quota_limits
 * @property list<string> $highlights PlanFeature values from PlanFeature::highlights().
 * @property list<string> $extra_features
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 *
 * @method static BillingPlanFactory factory($count = null, $state = [])
 *
 * @mixin \Eloquent
 */
#[Guarded(['id'])]
#[ObservedBy(BillingCatalogObserver::class)]
class BillingPlan extends Model
{
    /** @use HasFactory<BillingPlanFactory> */
    use HasFactory;

    #[\Override]
    protected static function booted(): void
    {
        static::updating(function (self $plan): void {
            if ($plan->isDirty('key')) {
                throw new LogicException('A billing plan key cannot change: it is stored in Stripe metadata and on every workspace.');
            }
        });
    }

    #[\Override]
    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'is_popular' => 'boolean',
            'contact_sales' => 'boolean',
            'monthly_price_cents' => 'integer',
            'yearly_price_cents' => 'integer',
            'legacy_stripe_price_ids' => 'array',
            'capabilities' => BillingPackageCapabilitiesData::class,
            'quota_limits' => 'array',
            'highlights' => 'array',
            'extra_features' => 'array',
        ];
    }

    /** The plan's current Stripe price for one interval, or null when it has none. */
    public function stripePriceId(BillingInterval $interval): ?string
    {
        $priceId = $interval->isYearly() ? $this->stripe_yearly_price_id : $this->stripe_monthly_price_id;

        return filled($priceId) ? $priceId : null;
    }

    /** The interval a Stripe price bills this plan at when it is a current price, or null. */
    public function currentIntervalForPrice(string $priceId): ?BillingInterval
    {
        return match (true) {
            $priceId === '' => null,
            $priceId === $this->stripe_monthly_price_id => BillingInterval::Monthly,
            $priceId === $this->stripe_yearly_price_id => BillingInterval::Yearly,
            default => null,
        };
    }

    /** The interval of one of the plan's older Stripe prices, or null. */
    public function legacyIntervalForPrice(string $priceId): ?BillingInterval
    {
        $interval = $this->legacy_stripe_price_ids[$priceId] ?? null;

        return is_string($interval) ? BillingInterval::tryFrom($interval) : null;
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function active(Builder $query): void
    {
        $query->where('is_active', true);
    }
}
