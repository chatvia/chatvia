<?php

declare(strict_types=1);

namespace Domain\Billing\Actions\Catalog;

use Domain\Billing\Data\Catalog\BillingPlanData;
use Domain\Billing\Data\Catalog\SavedBillingPlanData;
use Domain\Billing\Enums\PlanFeature;
use Domain\Billing\Exceptions\InvalidBillingCatalogException;
use Domain\Billing\Models\BillingPlan;
use Domain\Billing\Services\Catalog\StripeCatalogSync;
use Illuminate\Support\Facades\DB;

/**
 * Creates a plan, or saves an existing one, from the admin panel, then sends
 * it to Stripe ({@see StripeCatalogSync}): its product, and a new price for
 * each billing cycle whose amount changed. Existing subscribers keep the price
 * they pay; the old price id stays on the plan as a legacy price.
 *
 * Rules: the key is lower-case letters, digits and underscores and never
 * changes; there is always exactly one default plan, and it is active. Making
 * a plan the default takes the flag from the old default in the same
 * transaction (a partial unique index allows one default only).
 *
 * @throws InvalidBillingCatalogException when one of the rules is broken
 */
class SaveBillingPlanAction
{
    /** The format of a plan key: it is used in URLs, Stripe metadata and on every workspace. */
    public const string KEY_PATTERN = '/^[a-z][a-z0-9_]*$/';

    public const int KEY_MAX_LENGTH = 64;

    public function __construct(
        private readonly StripeCatalogSync $stripe,
    ) {}

    public function execute(BillingPlanData $data, ?BillingPlan $plan = null): SavedBillingPlanData
    {
        $this->ensureValid($data, $plan);

        $plan = DB::transaction(function () use ($data, $plan): BillingPlan {
            $plan ??= new BillingPlan(['key' => $data->key, 'legacy_stripe_price_ids' => []]);

            if ($data->isDefault) {
                $this->takeDefaultFromOtherPlans($plan);
            }

            $plan->fill([
                'name' => $data->name,
                'description' => $data->description,
                'icon' => $data->icon,
                'sort_order' => $data->sortOrder,
                'is_active' => $data->isActive,
                'is_default' => $data->isDefault,
                'is_popular' => $data->isPopular,
                'contact_sales' => $data->contactSales,
                'monthly_price_cents' => $data->monthlyPriceCents,
                'yearly_price_cents' => $data->yearlyPriceCents,
                'currency' => strtolower($data->currency),
                // Limits this form does not know about are kept.
                'quota_limits' => array_replace_recursive($plan->quota_limits ?? [], $data->limits->toQuotaLimits()),
                'capabilities' => $data->capabilities,
                'highlights' => array_map(fn (PlanFeature $feature): string => $feature->value, $data->highlights),
                'extra_features' => $data->extraFeatures,
            ])->save();

            return $plan;
        });

        return new SavedBillingPlanData(plan: $plan, stripe: $this->stripe->plan($plan));
    }

    private function ensureValid(BillingPlanData $data, ?BillingPlan $plan): void
    {
        if (! $plan instanceof BillingPlan) {
            $this->ensureNewKey($data->key);
        } elseif ($data->key !== $plan->key) {
            throw InvalidBillingCatalogException::keyChanged();
        }

        if ($data->isDefault && ! $data->isActive) {
            throw InvalidBillingCatalogException::archivedDefault();
        }

        // Turning the flag off would leave no default: another plan takes it instead.
        if ($plan?->is_default && ! $data->isDefault) {
            throw InvalidBillingCatalogException::defaultRequired();
        }

        if ($data->monthlyPriceCents < 0 || ($data->yearlyPriceCents ?? 0) < 0) {
            throw InvalidBillingCatalogException::negativePrice();
        }
    }

    private function ensureNewKey(string $key): void
    {
        if (strlen($key) > self::KEY_MAX_LENGTH || preg_match(self::KEY_PATTERN, $key) !== 1) {
            throw InvalidBillingCatalogException::invalidKey($key);
        }

        if (BillingPlan::query()->where('key', $key)->exists()) {
            throw InvalidBillingCatalogException::keyTaken($key);
        }
    }

    /** Saved one by one, so the catalog cache is cleared as for any other change. */
    private function takeDefaultFromOtherPlans(BillingPlan $plan): void
    {
        BillingPlan::query()
            ->where('is_default', true)
            ->when($plan->exists, fn ($query) => $query->whereKeyNot($plan->getKey()))
            ->get()
            ->each(fn (BillingPlan $other): bool => $other->update(['is_default' => false]));
    }
}
