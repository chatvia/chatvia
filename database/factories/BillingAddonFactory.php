<?php

declare(strict_types=1);

namespace Database\Factories;

use Domain\Billing\Models\BillingAddon;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Builds an active add-on row with a unique key and a test Stripe price. The
 * real add-ons (one row per enum value) come from the migration; use this for
 * rows a test needs on top of them.
 *
 * @extends Factory<BillingAddon>
 */
class BillingAddonFactory extends Factory
{
    #[\Override]
    protected $model = BillingAddon::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $key = 'addon_'.fake()->unique()->lexify('??????');

        return [
            'key' => $key,
            'name' => ucfirst($key),
            'description' => 'A test add-on.',
            'is_active' => true,
            'price_cents' => 500,
            'currency' => 'eur',
            'billing_unit' => 'per month',
            'icon' => 'bot',
            'unit_messages' => null,
            'stripe_price_id' => "price_{$key}",
            'legacy_stripe_price_ids' => [],
        ];
    }
}
