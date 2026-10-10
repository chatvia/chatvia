<?php

declare(strict_types=1);

namespace Domain\Billing\Services\Catalog;

use Domain\Billing\Data\Subscriptions\PlanSelectionData;
use Domain\Billing\Enums\Addon;
use Domain\Billing\Enums\MessageCreditAddon;
use Domain\Billing\Models\BillingAddon;
use Domain\Billing\Models\BillingPlan;
use Domain\Billing\Observers\BillingCatalogObserver;
use Illuminate\Container\Attributes\Scoped;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;

/**
 * The plans and add-ons (`billing_plans`, `billing_addons`): the one read path
 * for every billing reader.
 *
 * The rows are cached as plain attribute arrays, shared by every process, and
 * turned into models once per request or queued job. The class is bound as a
 * scoped instance, which Laravel drops before each queue job, so a long-running
 * worker never keeps an old catalog; for the same reason nothing that lives
 * longer (a singleton) may hold on to it. Saving or deleting a plan or add-on
 * clears the cache ({@see BillingCatalogObserver}); it also expires after an
 * hour, as a safety net for rows changed outside Eloquent.
 */
#[Scoped]
final class BillingCatalog
{
    public const string CACHE_KEY = 'billing_catalog';

    /** The plan new accounts start on when no plan is marked as the default. */
    public const string FALLBACK_PLAN_KEY = 'starter';

    private const int CACHE_SECONDS = 3600;

    /** @var array{plans: list<array<string, mixed>>, addons: list<array<string, mixed>>}|null */
    private ?array $rows = null;

    /** @var Collection<string, BillingPlan>|null */
    private ?Collection $plans = null;

    /** @var Collection<string, BillingAddon>|null */
    private ?Collection $addons = null;

    public function __construct(
        private readonly Cache $cache,
    ) {}

    /**
     * Every plan, archived ones included, in display order, keyed by plan key.
     *
     * @return Collection<string, BillingPlan>
     */
    public function plans(): Collection
    {
        return $this->plans ??= BillingPlan::hydrate($this->rows()['plans'])
            ->toBase()
            ->keyBy(fn (BillingPlan $plan): string => $plan->key);
    }

    /**
     * The plans a workspace can choose, in display order.
     *
     * @return Collection<string, BillingPlan>
     */
    public function activePlans(): Collection
    {
        return $this->plans()->filter(fn (BillingPlan $plan): bool => $plan->is_active);
    }

    /**
     * The plans a workspace can buy itself through Stripe Checkout: active
     * plans that are not sold through sales only.
     *
     * @return Collection<string, BillingPlan>
     */
    public function selfServePlans(): Collection
    {
        return $this->activePlans()->reject(fn (BillingPlan $plan): bool => $plan->contact_sales);
    }

    /** A plan by key, archived or not. */
    public function plan(string $key): ?BillingPlan
    {
        return $this->plans()->get($key);
    }

    /** The plan new accounts start on. */
    public function defaultPlan(): ?BillingPlan
    {
        return $this->plans()->first(fn (BillingPlan $plan): bool => $plan->is_default);
    }

    public function defaultPlanKey(): string
    {
        return $this->defaultPlan()?->key ?? self::FALLBACK_PLAN_KEY;
    }

    /**
     * The plan and billing cycle behind a Stripe price, or null when no plan
     * has the price. Current prices win over legacy ones, so a price that was
     * moved from one plan to another resolves to the plan that has it now.
     */
    public function planForPrice(string $priceId): ?PlanSelectionData
    {
        foreach ($this->plans() as $plan) {
            $interval = $plan->currentIntervalForPrice($priceId);

            if ($interval !== null) {
                return new PlanSelectionData(package: $plan->key, isYearly: $interval->isYearly());
            }
        }

        foreach ($this->plans() as $plan) {
            $interval = $plan->legacyIntervalForPrice($priceId);

            if ($interval !== null) {
                return new PlanSelectionData(package: $plan->key, isYearly: $interval->isYearly());
            }
        }

        return null;
    }

    /**
     * Every add-on, keyed by its enum value.
     *
     * @return Collection<string, BillingAddon>
     */
    public function addons(): Collection
    {
        return $this->addons ??= BillingAddon::hydrate($this->rows()['addons'])
            ->toBase()
            ->keyBy(fn (BillingAddon $addon): string => $addon->key);
    }

    public function addon(Addon|MessageCreditAddon $addon): ?BillingAddon
    {
        return $this->addons()->get($addon->value);
    }

    /** The add-on a Stripe price (current or legacy) belongs to, or null. */
    public function addonForPrice(string $priceId): ?BillingAddon
    {
        return $this->addons()->first(fn (BillingAddon $addon): bool => $addon->billsPrice($priceId));
    }

    /** Forgets the cached rows, here and in the shared cache. */
    public function flush(): void
    {
        $this->cache->forget(self::CACHE_KEY);

        $this->rows = null;
        $this->plans = null;
        $this->addons = null;
    }

    /**
     * @return array{plans: list<array<string, mixed>>, addons: list<array<string, mixed>>}
     */
    private function rows(): array
    {
        if ($this->rows !== null) {
            return $this->rows;
        }

        $cached = $this->cache->get(self::CACHE_KEY);

        if (is_array($cached) && isset($cached['plans'], $cached['addons'])) {
            return $this->rows = $cached;
        }

        $rows = [
            'plans' => $this->attributes(BillingPlan::query()->orderBy('sort_order')->orderBy('id')),
            'addons' => $this->attributes(BillingAddon::query()->orderBy('id')),
        ];

        // An empty catalog is never cached: a read that ran before the plans
        // were inserted must not hide them for an hour.
        if ($rows['plans'] !== []) {
            $this->cache->put(self::CACHE_KEY, $rows, self::CACHE_SECONDS);
        }

        return $this->rows = $rows;
    }

    /**
     * The raw column values, as the database returns them, so the cache holds
     * plain arrays rather than serialized models.
     *
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $query
     * @return list<array<string, mixed>>
     */
    private function attributes(Builder $query): array
    {
        return $query->toBase()->get()->map(fn (object $row): array => (array) $row)->values()->all();
    }
}
