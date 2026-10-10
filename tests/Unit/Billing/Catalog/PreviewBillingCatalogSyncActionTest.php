<?php

declare(strict_types=1);

use Domain\Billing\Actions\Catalog\PreviewBillingCatalogSyncAction;
use Domain\Billing\Enums\StripeSyncBlocker;
use Domain\Billing\Models\BillingAddon;
use Domain\Billing\Models\BillingPlan;
use Domain\Billing\Services\Catalog\StripeCatalogSync;
use Tests\Support\FakeStripeCatalogClient;

/**
 * "Check against Stripe" reads the live Stripe prices and reports every saved
 * amount that differs, without writing anything, in Stripe or in the database.
 */
beforeEach(function (): void {
    config(['billing.enabled' => true, 'cashier.secret' => 'sk_test_catalog_preview']);
    $this->stripe = FakeStripeCatalogClient::bind();
});

it('reports a saved amount that differs from the live price, and writes nothing', function (): void {
    updateBillingAddon('auto_recharge_credits', ['price_cents' => 1000]);
    updateBillingAddon('extra_message_credits', ['price_cents' => 900]);
    $this->stripe->seedLiveCatalog(creditPackAmount: 900);
    $before = [BillingPlan::query()->orderBy('id')->get()->toArray(), BillingAddon::query()->orderBy('id')->get()->toArray()];

    $result = app(PreviewBillingCatalogSyncAction::class)->execute();

    expect($this->stripe->writes())->toBe(0)
        ->and([BillingPlan::query()->orderBy('id')->get()->toArray(), BillingAddon::query()->orderBy('id')->get()->toArray()])->toBe($before)
        ->and($result->priceChanges)->toHaveCount(1);

    $change = $result->priceChanges[0];

    expect($change->item)->toBe('Auto Recharge Credits')
        ->and($change->slot)->toBe('one_time')
        ->and($change->oldPriceId)->toBe('price_credits')
        ->and($change->oldAmount)->toBe(900)
        ->and($change->newAmount)->toBe(1000)
        ->and($result->retiredPriceIds)->toBe(['price_credits']);
});

it('reports no price change when every live price matches', function (): void {
    updateBillingAddon('auto_recharge_credits', ['price_cents' => 900]);
    updateBillingAddon('extra_message_credits', ['price_cents' => 900]);
    $this->stripe->seedLiveCatalog(creditPackAmount: 900);

    $result = app(PreviewBillingCatalogSyncAction::class)->execute();

    expect($result->priceChanges)->toBe([])
        ->and($result->createdPriceIds)->toBe([])
        ->and($result->retiredPriceIds)->toBe([])
        // The products would get the plan and add-on names and metadata.
        ->and($result->updatedProductIds)->not->toBe([])
        ->and($this->stripe->writes())->toBe(0);
});

it('leaves the plan it previews untouched', function (): void {
    $plan = BillingPlan::factory()->create(['stripe_product_id' => null, 'stripe_monthly_price_id' => null, 'stripe_yearly_price_id' => null]);

    $result = app(StripeCatalogSync::class)->previewPlan($plan);

    expect($result->createdProductIds)->toHaveCount(1)
        ->and($result->createdPriceIds)->toHaveCount(2)
        ->and($plan->isDirty())->toBeFalse()
        ->and($plan->stripe_monthly_price_id)->toBeNull()
        ->and($plan->refresh()->stripe_product_id)->toBeNull()
        ->and($this->stripe->writes())->toBe(0);
});

it('checks nothing while billing is off', function (): void {
    config(['billing.enabled' => false]);

    expect(app(PreviewBillingCatalogSyncAction::class)->execute()->blocker)->toBe(StripeSyncBlocker::BillingOff);
});
