<?php

declare(strict_types=1);

use Domain\Billing\Enums\StripeSyncBlocker;
use Domain\Billing\Models\BillingAddon;
use Domain\Billing\Models\BillingPlan;
use Domain\Billing\Services\Catalog\BillingCatalog;
use Domain\Billing\Services\Catalog\StripeCatalogSync;
use Tests\Support\FakeStripeCatalogClient;

/**
 * The sync of plans and add-ons with Stripe: a product per item, a new price
 * only when the live Stripe price charges something else, old prices kept as
 * legacy prices, and no Stripe call at all while billing is off or no secret
 * key is saved.
 */
beforeEach(function (): void {
    config(['billing.enabled' => true, 'cashier.secret' => 'sk_test_catalog_sync']);
    $this->stripe = FakeStripeCatalogClient::bind();
});

/**
 * A plan already sold in Stripe at 49.00 / 468.00, as a migrated plan is.
 *
 * @param  array<string, mixed>  $attributes
 */
function syncedPlan(FakeStripeCatalogClient $stripe, array $attributes = [], array $productMetadata = []): BillingPlan
{
    $stripe->seedProduct('prod_live', 'Chatvia Starter', $productMetadata);
    $stripe->seedPrice('price_live_m', 'prod_live', 4900, ['interval' => 'month']);
    $stripe->seedPrice('price_live_y', 'prod_live', 46800, ['interval' => 'year']);

    return BillingPlan::factory()->create([
        'name' => 'Starter Plus',
        'description' => 'For small teams.',
        'monthly_price_cents' => 4900,
        'yearly_price_cents' => 46800,
        'stripe_product_id' => null,
        'stripe_monthly_price_id' => 'price_live_m',
        'stripe_yearly_price_id' => 'price_live_y',
        ...$attributes,
    ]);
}

it('keeps the live prices when their amounts match, and only adopts and names the product', function (): void {
    $plan = syncedPlan($this->stripe);

    $result = app(StripeCatalogSync::class)->plan($plan);
    $plan->refresh();

    expect($this->stripe->createdPrices)->toBe([])
        ->and($this->stripe->createdProducts)->toBe([])
        ->and($plan->stripe_product_id)->toBe('prod_live')
        ->and($plan->stripe_monthly_price_id)->toBe('price_live_m')
        ->and($plan->stripe_yearly_price_id)->toBe('price_live_y')
        ->and($plan->legacy_stripe_price_ids)->toBe([])
        ->and($result->replacedPrices())->toBeFalse()
        ->and($result->updatedProductIds)->toBe(['prod_live']);

    $product = $this->stripe->productStore['prod_live'];
    expect($product->name)->toBe('Starter Plus')
        ->and($product->description)->toBe('For small teams.')
        ->and($product->metadata->toArray())->toBe(['chatvia_kind' => 'plan', 'chatvia_key' => $plan->key]);
});

it('changes nothing in Stripe when it runs again', function (): void {
    $plan = syncedPlan($this->stripe);
    app(StripeCatalogSync::class)->plan($plan);
    $writes = $this->stripe->writes();

    $again = app(StripeCatalogSync::class)->plan($plan->refresh());

    expect($this->stripe->writes())->toBe($writes)
        ->and($again->changedStripe())->toBeFalse()
        ->and($again->failed())->toBeFalse();
});

it('creates a new price when the amount changed and keeps the old one as a legacy price', function (): void {
    $plan = syncedPlan($this->stripe, ['monthly_price_cents' => 5900]);

    $result = app(StripeCatalogSync::class)->plan($plan);
    $plan->refresh();

    expect($this->stripe->createdPrices)->toHaveCount(1);
    $params = $this->stripe->createdPrices[0];

    expect($params['product'])->toBe('prod_live')
        ->and($params['unit_amount'])->toBe(5900)
        ->and($params['currency'])->toBe('eur')
        ->and($params['recurring'])->toBe(['interval' => 'month'])
        ->and($params['metadata'])->toBe(['chatvia_kind' => 'plan', 'chatvia_key' => $plan->key, 'chatvia_slot' => 'monthly'])
        ->and($plan->stripe_monthly_price_id)->toBe($result->createdPriceIds[0])
        ->and($plan->stripe_yearly_price_id)->toBe('price_live_y')
        ->and($plan->legacy_stripe_price_ids)->toBe(['price_live_m' => 'monthly'])
        ->and($result->retiredPriceIds)->toBe(['price_live_m'])
        ->and($result->replacedPrices())->toBeTrue();
});

it('still maps the old price to the plan after a price change', function (): void {
    $plan = syncedPlan($this->stripe, ['yearly_price_cents' => 50000]);

    app(StripeCatalogSync::class)->plan($plan);

    $selection = app(BillingCatalog::class)->planForPrice('price_live_y');

    expect($selection?->package)->toBe($plan->key)
        ->and($selection?->isYearly)->toBeTrue();
});

it('replaces a price whose currency or billing period is not the saved one', function (): void {
    $plan = syncedPlan($this->stripe, ['currency' => 'usd']);
    $this->stripe->seedPrice('price_live_y', 'prod_live', 46800, ['interval' => 'month', 'interval_count' => 12], 'usd');

    app(StripeCatalogSync::class)->plan($plan);
    $plan->refresh();

    expect(array_column($this->stripe->createdPrices, 'currency'))->toBe(['usd', 'usd'])
        ->and($this->stripe->createdPrices[1]['recurring'])->toBe(['interval' => 'year'])
        ->and($plan->legacy_stripe_price_ids)->toBe(['price_live_m' => 'monthly', 'price_live_y' => 'yearly']);
});

it('replaces a price that was archived in Stripe', function (): void {
    $plan = syncedPlan($this->stripe);
    $this->stripe->seedPrice('price_live_m', 'prod_live', 4900, ['interval' => 'month'], overrides: ['active' => false]);

    app(StripeCatalogSync::class)->plan($plan);

    expect($this->stripe->createdPrices)->toHaveCount(1)
        ->and($plan->refresh()->legacy_stripe_price_ids)->toBe(['price_live_m' => 'monthly']);
});

it('creates a new price when Stripe does not know the current one', function (): void {
    $plan = syncedPlan($this->stripe);
    unset($this->stripe->priceStore['price_live_m']);

    app(StripeCatalogSync::class)->plan($plan);

    expect($this->stripe->createdPrices)->toHaveCount(1)
        ->and($this->stripe->createdPrices[0]['unit_amount'])->toBe(4900)
        ->and($plan->refresh()->legacy_stripe_price_ids)->toBe(['price_live_m' => 'monthly']);
});

it('keeps the tax behaviour of the price it replaces', function (): void {
    $plan = syncedPlan($this->stripe, ['monthly_price_cents' => 5900]);
    $this->stripe->seedPrice('price_live_m', 'prod_live', 4900, ['interval' => 'month'], overrides: ['tax_behavior' => 'exclusive']);

    app(StripeCatalogSync::class)->plan($plan);

    expect($this->stripe->createdPrices[0]['tax_behavior'])->toBe('exclusive');
});

it('stops selling a billing cycle without an amount, keeping its price as a legacy price', function (): void {
    $plan = syncedPlan($this->stripe, ['yearly_price_cents' => null]);

    $result = app(StripeCatalogSync::class)->plan($plan);
    $plan->refresh();

    expect($this->stripe->createdPrices)->toBe([])
        ->and($plan->stripe_yearly_price_id)->toBeNull()
        ->and($plan->stripe_monthly_price_id)->toBe('price_live_m')
        ->and($plan->legacy_stripe_price_ids)->toBe(['price_live_y' => 'yearly'])
        ->and($result->retiredPriceIds)->toBe(['price_live_y']);
});

it('creates a product and both prices for a new plan', function (): void {
    $plan = BillingPlan::factory()->create([
        'name' => 'Growth',
        'description' => null,
        'monthly_price_cents' => 9900,
        'yearly_price_cents' => 99000,
        'stripe_monthly_price_id' => null,
        'stripe_yearly_price_id' => null,
    ]);

    $result = app(StripeCatalogSync::class)->plan($plan);
    $plan->refresh();

    expect($this->stripe->createdProducts)->toBe([[
        'name' => 'Growth',
        'metadata' => ['chatvia_kind' => 'plan', 'chatvia_key' => $plan->key],
    ]])
        ->and(array_column($this->stripe->createdPrices, 'unit_amount'))->toBe([9900, 99000])
        ->and(array_column($this->stripe->createdPrices, 'recurring'))->toBe([['interval' => 'month'], ['interval' => 'year']])
        ->and($plan->stripe_product_id)->toBe($result->createdProductIds[0])
        ->and($plan->stripe_monthly_price_id)->toBe($result->createdPriceIds[0])
        ->and($plan->stripe_yearly_price_id)->toBe($result->createdPriceIds[1])
        ->and($plan->legacy_stripe_price_ids)->toBe([])
        ->and($result->replacedPrices())->toBeFalse();
});

it('gives a contact-sales plan no product and no price', function (): void {
    $plan = BillingPlan::factory()->contactSales()->create(['monthly_price_cents' => 0, 'yearly_price_cents' => 0]);

    $result = app(StripeCatalogSync::class)->plan($plan);

    expect($this->stripe->writes())->toBe(0)
        ->and($result->changedStripe())->toBeFalse()
        ->and($plan->refresh()->stripe_product_id)->toBeNull();
});

it('does not take over a product that belongs to another plan', function (): void {
    $plan = syncedPlan($this->stripe, productMetadata: ['chatvia_kind' => 'plan', 'chatvia_key' => 'another_plan']);

    app(StripeCatalogSync::class)->plan($plan);

    expect($this->stripe->createdProducts)->toHaveCount(1)
        ->and($plan->refresh()->stripe_product_id)->not->toBe('prod_live')
        ->and($this->stripe->productStore['prod_live']->name)->toBe('Chatvia Starter')
        // The prices still charge the right amounts, so they stay.
        ->and($plan->stripe_monthly_price_id)->toBe('price_live_m')
        ->and($this->stripe->createdPrices)->toBe([]);
});

it('keeps what Stripe created before an error and reports the error', function (): void {
    $plan = syncedPlan($this->stripe, ['monthly_price_cents' => 5900, 'yearly_price_cents' => 59000]);
    $this->stripe->failPriceCreateFor = 59000;

    $result = app(StripeCatalogSync::class)->plan($plan);
    $plan->refresh();

    expect($result->failed())->toBeTrue()
        ->and($result->failure)->toBe('Could not connect to Stripe.')
        ->and($plan->stripe_monthly_price_id)->toBe($result->createdPriceIds[0])
        ->and($plan->stripe_yearly_price_id)->toBe('price_live_y')
        ->and($plan->legacy_stripe_price_ids)->toBe(['price_live_m' => 'monthly']);
});

it('sends nothing while billing is off', function (): void {
    config(['billing.enabled' => false]);
    $plan = syncedPlan($this->stripe, ['monthly_price_cents' => 5900]);

    $result = app(StripeCatalogSync::class)->plan($plan);

    expect($result->blocker)->toBe(StripeSyncBlocker::BillingOff)
        ->and($result->wasSent())->toBeFalse()
        ->and($this->stripe->writes())->toBe(0)
        ->and($plan->refresh()->stripe_monthly_price_id)->toBe('price_live_m');
});

it('sends nothing without a Stripe secret key', function (): void {
    config(['cashier.secret' => null]);
    $addon = updateBillingAddon('extra_agents', ['price_cents' => 700]);

    $result = app(StripeCatalogSync::class)->addon($addon);

    expect($result->blocker)->toBe(StripeSyncBlocker::NoSecretKey)
        ->and($this->stripe->writes())->toBe(0);
});

it('sells a subscription add-on with a monthly price and lists the old price as a legacy price', function (): void {
    $this->stripe->seedProduct('prod_agents', 'Extra agents');
    $this->stripe->seedPrice('price_agents', 'prod_agents', 500, ['interval' => 'month']);
    $addon = updateBillingAddon('extra_agents', [
        'price_cents' => 700,
        'stripe_product_id' => null,
        'stripe_price_id' => 'price_agents',
        'legacy_stripe_price_ids' => ['price_agents_2024'],
    ]);

    $result = app(StripeCatalogSync::class)->addon($addon);
    $addon->refresh();

    expect($this->stripe->createdPrices)->toHaveCount(1)
        ->and($this->stripe->createdPrices[0]['recurring'])->toBe(['interval' => 'month'])
        ->and($this->stripe->createdPrices[0]['unit_amount'])->toBe(700)
        ->and($this->stripe->createdPrices[0]['product'])->toBe('prod_agents')
        ->and($addon->stripe_price_id)->toBe($result->createdPriceIds[0])
        ->and($addon->legacy_stripe_price_ids)->toBe(['price_agents_2024', 'price_agents'])
        ->and(app(BillingCatalog::class)->addonForPrice('price_agents')?->key)->toBe('extra_agents');
});

it('sells a message-credit pack with a one-time price', function (): void {
    $addon = updateBillingAddon('extra_message_credits', ['price_cents' => 1200, 'stripe_product_id' => null, 'stripe_price_id' => null]);

    $result = app(StripeCatalogSync::class)->addon($addon);

    expect($this->stripe->createdPrices)->toHaveCount(1)
        ->and($this->stripe->createdPrices[0])->not->toHaveKey('recurring')
        ->and($this->stripe->createdPrices[0]['unit_amount'])->toBe(1200)
        ->and($this->stripe->createdPrices[0]['metadata']['chatvia_slot'])->toBe('one_time')
        ->and($addon->refresh()->stripe_price_id)->toBe($result->createdPriceIds[0])
        ->and($addon->legacy_stripe_price_ids)->toBe([]);
});

it('keeps the price two credit packs share when it charges their amount, giving the second pack its own product', function (): void {
    $this->stripe->seedProduct('prod_credits', '1,000 messages');
    $this->stripe->seedPrice('price_credits', 'prod_credits', 900, null);
    $first = updateBillingAddon('auto_recharge_credits', ['price_cents' => 900, 'stripe_product_id' => null, 'stripe_price_id' => 'price_credits']);
    $second = updateBillingAddon('extra_message_credits', ['price_cents' => 900, 'stripe_product_id' => null, 'stripe_price_id' => 'price_credits']);

    app(StripeCatalogSync::class)->addon($first);
    app(StripeCatalogSync::class)->addon($second);

    expect($this->stripe->createdPrices)->toBe([])
        ->and($first->refresh()->stripe_product_id)->toBe('prod_credits')
        ->and($second->refresh()->stripe_product_id)->not->toBe('prod_credits')
        ->and($this->stripe->createdProducts)->toHaveCount(1)
        ->and($first->stripe_price_id)->toBe('price_credits')
        ->and($second->stripe_price_id)->toBe('price_credits')
        ->and($this->stripe->productStore['prod_credits']->name)->toBe($first->name);
});

it('keeps an add-on without a price unsold in Stripe', function (): void {
    $addon = BillingAddon::factory()->create(['price_cents' => 0, 'stripe_price_id' => null, 'stripe_product_id' => null]);

    app(StripeCatalogSync::class)->addon($addon);

    expect($this->stripe->writes())->toBe(0);
});
