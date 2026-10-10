<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Billing\Data\Resources\Package\BillingPackageCapabilitiesData;
use Domain\Billing\Models\BillingPlan;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds an active, self-serve plan with a unique key, test Stripe prices,
 * every capability off and small limits. It is never the default plan (the
 * migrated Starter plan is).
 *
 * @extends Factory<BillingPlan>
 */
class BillingPlanFactory extends Factory
{
    #[\Override]
    protected $model = BillingPlan::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = 'plan_'.fake()->unique()->lexify('??????');

        return [
            'key' => $key,
            'name' => ucfirst($key),
            'description' => 'A test plan.',
            'icon' => 'zap',
            'sort_order' => 100,
            'is_active' => true,
            'is_default' => false,
            'is_popular' => false,
            'contact_sales' => false,
            'monthly_price_cents' => 1000,
            'yearly_price_cents' => 10000,
            'currency' => 'eur',
            'stripe_monthly_price_id' => "price_{$key}_monthly",
            'stripe_yearly_price_id' => "price_{$key}_yearly",
            'legacy_stripe_price_ids' => [],
            'capabilities' => new BillingPackageCapabilitiesData,
            'quota_limits' => [
                'monthly' => ['messages_limit' => 1000],
                'yearly' => ['messages_limit' => 12000],
                'fixed' => [
                    'agents_limit' => 1,
                    'pages_limit' => 100,
                    'minutes_limit' => 10,
                    'links_limit' => 100,
                    'custom_domains_limit' => 0,
                    'workspace_users_limit' => 1,
                    'teams_limit' => 1,
                ],
            ],
            'extra_features' => [],
        ];
    }

    /** No longer offered, but still resolves for existing subscribers. */
    public function archived(): static
    {
        return $this->state(fn (): array => ['is_active' => false]);
    }

    /** Sold through sales only: no Stripe prices. */
    public function contactSales(): static
    {
        return $this->state(fn (): array => [
            'contact_sales' => true,
            'stripe_monthly_price_id' => null,
            'stripe_yearly_price_id' => null,
        ]);
    }
}
