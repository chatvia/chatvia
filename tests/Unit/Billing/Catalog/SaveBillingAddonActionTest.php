<?php

declare(strict_types=1);

use Domain\Billing\Actions\Catalog\SaveBillingAddonAction;
use Domain\Billing\Data\Catalog\BillingAddonData;
use Domain\Billing\Enums\Addon;
use Domain\Billing\Enums\StripeSyncBlocker;
use Domain\Billing\Exceptions\InvalidBillingCatalogException;
use Tests\Support\FakeStripeCatalogClient;

/**
 * Editing an add-on from the admin panel, and the Stripe sync that follows.
 */
beforeEach(function (): void {
    $this->stripe = FakeStripeCatalogClient::bind();
});

function addonData(int $priceCents = 700, bool $isActive = true): BillingAddonData
{
    return new BillingAddonData(
        name: 'More agents',
        description: 'Add agents beyond your plan.',
        isActive: $isActive,
        priceCents: $priceCents,
        currency: 'eur',
        billingUnit: 'per agent/month',
        icon: 'bot',
    );
}

it('saves the texts and price, and sends nothing to Stripe while billing is off', function (): void {
    config(['billing.enabled' => false]);
    $addon = updateBillingAddon('extra_agents', ['stripe_price_id' => 'price_agents']);

    $saved = app(SaveBillingAddonAction::class)->execute(addonData(isActive: false), $addon);
    $addon->refresh();

    expect($addon->name)->toBe('More agents')
        ->and($addon->description)->toBe('Add agents beyond your plan.')
        ->and($addon->price_cents)->toBe(700)
        ->and($addon->billing_unit)->toBe('per agent/month')
        ->and($addon->is_active)->toBeFalse()
        ->and($addon->stripe_price_id)->toBe('price_agents')
        // Not sold: workspaces cannot buy it, but the price stays for subscribers.
        ->and(Addon::ExtraAgents->stripePriceId())->toBeNull()
        ->and($saved->stripe->blocker)->toBe(StripeSyncBlocker::BillingOff)
        ->and($this->stripe->writes())->toBe(0);
});

it('creates a new monthly price for a changed amount when billing is on', function (): void {
    config(['billing.enabled' => true, 'cashier.secret' => 'sk_test_x']);
    $this->stripe->seedProduct('prod_agents', 'Extra AI Agents');
    $this->stripe->seedPrice('price_agents', 'prod_agents', 500, ['interval' => 'month']);
    $addon = updateBillingAddon('extra_agents', ['stripe_product_id' => null, 'stripe_price_id' => 'price_agents']);

    $saved = app(SaveBillingAddonAction::class)->execute(addonData(), $addon);
    $addon->refresh();

    expect($saved->stripe->replacedPrices())->toBeTrue()
        ->and($this->stripe->createdPrices[0]['recurring'])->toBe(['interval' => 'month'])
        ->and($addon->stripe_product_id)->toBe('prod_agents')
        ->and($addon->stripe_price_id)->toBe($saved->stripe->createdPriceIds[0])
        ->and($addon->legacy_stripe_price_ids)->toBe(['price_agents'])
        ->and(Addon::ExtraAgents->stripePriceId())->toBe($addon->stripe_price_id)
        ->and($this->stripe->productStore['prod_agents']->name)->toBe('More agents');
});

it('refuses a negative price', function (): void {
    $addon = updateBillingAddon('members', []);

    expect(fn () => app(SaveBillingAddonAction::class)->execute(addonData(-100), $addon))
        ->toThrow(InvalidBillingCatalogException::class, 'cannot be negative');
});
