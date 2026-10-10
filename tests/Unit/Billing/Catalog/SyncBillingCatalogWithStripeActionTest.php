<?php

declare(strict_types=1);

use Domain\Billing\Actions\Catalog\SyncBillingCatalogWithStripeAction;
use Domain\Billing\Enums\StripeSyncBlocker;
use Domain\Billing\Models\BillingAddon;
use Domain\Billing\Models\BillingPlan;
use Tests\Support\FakeStripeCatalogClient;

/**
 * "Sync with Stripe" for the whole catalog, run against a Stripe account like
 * production's: every migrated plan and add-on already has live prices and
 * products (named and described in Stripe, without Chatvia metadata), and the
 * two credit packs share one one-time price.
 */
beforeEach(function (): void {
    config(['billing.enabled' => true, 'cashier.secret' => 'sk_test_catalog']);
    $this->stripe = FakeStripeCatalogClient::bind();
});

it('creates no price when every live Stripe price charges the saved amount', function (): void {
    updateBillingAddon('auto_recharge_credits', ['price_cents' => 900]);
    updateBillingAddon('extra_message_credits', ['price_cents' => 900]);
    $this->stripe->seedLiveCatalog(creditPackAmount: 900);

    $result = app(SyncBillingCatalogWithStripeAction::class)->execute();

    expect($result->failed())->toBeFalse()
        ->and($this->stripe->createdPrices)->toBe([])
        ->and($result->replacedPrices())->toBeFalse()
        // The second credit pack gets its own (empty) product; the contact-sales plan gets none.
        ->and($this->stripe->createdProducts)->toHaveCount(1)
        ->and($this->stripe->createdProducts[0]['metadata']['chatvia_key'])->toBe('extra_message_credits')
        ->and(BillingPlan::query()->where('key', 'enterprise')->value('stripe_product_id'))->toBeNull()
        ->and(BillingPlan::query()->where('key', 'starter')->value('stripe_product_id'))->toBe('prod_starter')
        ->and(BillingPlan::query()->where('key', 'starter')->value('stripe_monthly_price_id'))->toBe('price_starter_m')
        ->and(BillingAddon::query()->where('key', 'extra_message_credits')->value('stripe_price_id'))->toBe('price_credits');

    $writes = $this->stripe->writes();
    $again = app(SyncBillingCatalogWithStripeAction::class)->execute();

    expect($this->stripe->writes())->toBe($writes)
        ->and($again->changedStripe())->toBeFalse();
});

it('gives a credit pack whose saved amount differs from the shared live price a price of its own', function (): void {
    updateBillingAddon('auto_recharge_credits', ['price_cents' => 1000]);
    updateBillingAddon('extra_message_credits', ['price_cents' => 900]);
    $this->stripe->seedLiveCatalog(creditPackAmount: 900);

    $result = app(SyncBillingCatalogWithStripeAction::class)->execute();

    $autoRecharge = BillingAddon::query()->where('key', 'auto_recharge_credits')->firstOrFail();
    $extraCredits = BillingAddon::query()->where('key', 'extra_message_credits')->firstOrFail();

    expect($this->stripe->createdPrices)->toHaveCount(1)
        ->and($this->stripe->createdPrices[0]['unit_amount'])->toBe(1000)
        ->and($this->stripe->createdPrices[0])->not->toHaveKey('recurring')
        ->and($autoRecharge->stripe_price_id)->toBe($result->createdPriceIds[0])
        ->and($autoRecharge->legacy_stripe_price_ids)->toBe(['price_credits'])
        ->and($extraCredits->stripe_price_id)->toBe('price_credits')
        ->and($result->retiredPriceIds)->toBe(['price_credits']);
});

it('sends nothing while billing is off', function (): void {
    config(['billing.enabled' => false]);

    $result = app(SyncBillingCatalogWithStripeAction::class)->execute();

    expect($result->blocker)->toBe(StripeSyncBlocker::BillingOff)
        ->and($this->stripe->writes())->toBe(0);
});

it('stops at the first Stripe error', function (): void {
    $this->stripe->seedLiveCatalog(creditPackAmount: 900);
    $starter = BillingPlan::query()->where('key', 'starter')->firstOrFail();
    $starter->update(['monthly_price_cents' => 5100]);
    updateBillingAddon('extra_agents', ['price_cents' => 800]);
    $this->stripe->failPriceCreateFor = 5100;

    $result = app(SyncBillingCatalogWithStripeAction::class)->execute();

    expect($result->failure)->toBe('Could not connect to Stripe.')
        ->and($this->stripe->createdPrices)->toBe([])
        ->and(BillingAddon::query()->where('key', 'extra_agents')->value('stripe_price_id'))->toBe('price_extra_agents');
});
