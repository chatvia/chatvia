<?php

declare(strict_types=1);

use Domain\Billing\Actions\Catalog\SaveBillingPlanAction;
use Domain\Billing\Data\Catalog\BillingPlanData;
use Domain\Billing\Data\Catalog\BillingPlanLimitsData;
use Domain\Billing\Data\Resources\Package\BillingPackageCapabilitiesData;
use Domain\Billing\Enums\StripeSyncBlocker;
use Domain\Billing\Exceptions\InvalidBillingCatalogException;
use Domain\Billing\Models\BillingPlan;
use Domain\Billing\Services\Catalog\BillingCatalog;
use Tests\Support\FakeStripeCatalogClient;

/**
 * Creating and editing a plan from the admin panel: the key rules, exactly
 * one active default plan, and the Stripe sync that follows a save.
 */
beforeEach(function (): void {
    $this->stripe = FakeStripeCatalogClient::bind();
});

/**
 * @param  array<string, mixed>  $overrides  BillingPlanData constructor arguments
 */
function planData(array $overrides = []): BillingPlanData
{
    return new BillingPlanData(...[
        'key' => 'growth',
        'name' => 'Growth',
        'description' => 'For teams that grow.',
        'icon' => 'star',
        'sortOrder' => 5,
        'isActive' => true,
        'isDefault' => false,
        'isPopular' => true,
        'contactSales' => false,
        'monthlyPriceCents' => 9900,
        'yearlyPriceCents' => 99000,
        'currency' => 'eur',
        'limits' => new BillingPlanLimitsData(
            monthlyMessages: 8000,
            yearlyMessages: 96000,
            agents: 5,
            pages: 3000,
            minutes: 1000,
            links: 3000,
            customDomains: 1,
            workspaceUsers: 10,
            teams: 3,
        ),
        'capabilities' => new BillingPackageCapabilitiesData(sentiment: true, handoff: true, retentionDays: 120),
        'extraFeatures' => ['Priority support'],
        ...$overrides,
    ]);
}

it('creates a plan with its limits, features and extra lines', function (): void {
    $saved = app(SaveBillingPlanAction::class)->execute(planData());
    $plan = $saved->plan->refresh();

    expect($plan->key)->toBe('growth')
        ->and($plan->name)->toBe('Growth')
        ->and($plan->monthly_price_cents)->toBe(9900)
        ->and($plan->yearly_price_cents)->toBe(99000)
        ->and($plan->is_popular)->toBeTrue()
        // jsonb does not keep the key order.
        ->and($plan->quota_limits)->toEqual([
            'monthly' => ['messages_limit' => 8000],
            'yearly' => ['messages_limit' => 96000],
            'fixed' => [
                'agents_limit' => 5,
                'pages_limit' => 3000,
                'minutes_limit' => 1000,
                'links_limit' => 3000,
                'custom_domains_limit' => 1,
                'workspace_users_limit' => 10,
                'teams_limit' => 3,
            ],
        ])
        ->and($plan->capabilities->sentiment)->toBeTrue()
        ->and($plan->capabilities->tone)->toBeFalse()
        ->and($plan->capabilities->retentionDays)->toBe(120)
        ->and($plan->extra_features)->toBe(['Priority support'])
        ->and(app(BillingCatalog::class)->plan('growth')?->name)->toBe('Growth');
});

it('saves locally and sends nothing to Stripe while billing is off', function (): void {
    config(['billing.enabled' => false, 'cashier.secret' => 'sk_test_x']);

    $saved = app(SaveBillingPlanAction::class)->execute(planData());

    expect($saved->stripe->blocker)->toBe(StripeSyncBlocker::BillingOff)
        ->and($this->stripe->writes())->toBe(0)
        ->and($saved->plan->stripe_product_id)->toBeNull();
});

it('sends nothing to Stripe without a secret key', function (): void {
    config(['billing.enabled' => true, 'cashier.secret' => '']);

    $saved = app(SaveBillingPlanAction::class)->execute(planData());

    expect($saved->stripe->blocker)->toBe(StripeSyncBlocker::NoSecretKey)
        ->and($this->stripe->writes())->toBe(0);
});

it('creates the Stripe product and prices when billing is on', function (): void {
    config(['billing.enabled' => true, 'cashier.secret' => 'sk_test_x']);

    $saved = app(SaveBillingPlanAction::class)->execute(planData());
    $plan = $saved->plan->refresh();

    expect($saved->stripe->wasSent())->toBeTrue()
        ->and($this->stripe->createdProducts)->toHaveCount(1)
        ->and(array_column($this->stripe->createdPrices, 'unit_amount'))->toBe([9900, 99000])
        ->and($plan->stripe_product_id)->not->toBeNull()
        ->and($plan->stripe_monthly_price_id)->not->toBeNull()
        ->and($plan->stripe_yearly_price_id)->not->toBeNull();
});

it('creates a new Stripe price for a changed amount and keeps the old one for subscribers', function (): void {
    config(['billing.enabled' => true, 'cashier.secret' => 'sk_test_x']);
    $this->stripe->seedProduct('prod_growth', 'Growth', ['chatvia_kind' => 'plan', 'chatvia_key' => 'growth'], 'For teams that grow.');
    $this->stripe->seedPrice('price_growth_m', 'prod_growth', 9900, ['interval' => 'month']);
    $this->stripe->seedPrice('price_growth_y', 'prod_growth', 99000, ['interval' => 'year']);
    $plan = BillingPlan::factory()->create([
        'key' => 'growth',
        'name' => 'Growth',
        'description' => 'For teams that grow.',
        'stripe_product_id' => 'prod_growth',
        'stripe_monthly_price_id' => 'price_growth_m',
        'stripe_yearly_price_id' => 'price_growth_y',
    ]);

    $saved = app(SaveBillingPlanAction::class)->execute(planData(['monthlyPriceCents' => 12900]), $plan);
    $plan->refresh();

    expect($this->stripe->createdPrices)->toHaveCount(1)
        ->and($this->stripe->createdPrices[0]['unit_amount'])->toBe(12900)
        ->and($plan->stripe_monthly_price_id)->toBe($saved->stripe->createdPriceIds[0])
        ->and($plan->stripe_yearly_price_id)->toBe('price_growth_y')
        ->and($plan->legacy_stripe_price_ids)->toBe(['price_growth_m' => 'monthly'])
        ->and($saved->stripe->replacedPrices())->toBeTrue();
});

it('keeps limits the form does not know about', function (): void {
    $plan = BillingPlan::factory()->create(['key' => 'growth']);
    $plan->update(['quota_limits' => [...$plan->quota_limits, 'fixed' => [...$plan->quota_limits['fixed'], 'future_limit' => 7]]]);

    app(SaveBillingPlanAction::class)->execute(planData(), $plan);

    expect($plan->refresh()->quota_limits['fixed']['future_limit'])->toBe(7)
        ->and($plan->quota_limits['fixed']['agents_limit'])->toBe(5);
});

it('moves the default flag from the old default plan', function (): void {
    $oldDefault = app(BillingCatalog::class)->defaultPlan();

    app(SaveBillingPlanAction::class)->execute(planData(['isDefault' => true]));

    expect($oldDefault?->refresh()->is_default)->toBeFalse()
        ->and(BillingPlan::query()->where('is_default', true)->pluck('key')->all())->toBe(['growth'])
        ->and(app(BillingCatalog::class)->defaultPlanKey())->toBe('growth');
});

it('refuses an invalid key', function (string $key): void {
    expect(fn () => app(SaveBillingPlanAction::class)->execute(planData(['key' => $key])))
        ->toThrow(InvalidBillingCatalogException::class, 'is not valid');

    expect(BillingPlan::query()->where('key', $key)->exists())->toBeFalse();
})->with(['Growth', '1plan', 'growth-plan', 'growth plan', '', str_repeat('a', 65)]);

it('refuses a key that is taken', function (): void {
    expect(fn () => app(SaveBillingPlanAction::class)->execute(planData(['key' => 'starter'])))
        ->toThrow(InvalidBillingCatalogException::class, 'already exists');
});

it('refuses to change the key of a plan', function (): void {
    $plan = BillingPlan::factory()->create();

    expect(fn () => app(SaveBillingPlanAction::class)->execute(planData(['key' => 'renamed']), $plan))
        ->toThrow(InvalidBillingCatalogException::class, 'cannot change');
});

it('refuses an archived default plan', function (): void {
    expect(fn () => app(SaveBillingPlanAction::class)->execute(planData(['isDefault' => true, 'isActive' => false])))
        ->toThrow(InvalidBillingCatalogException::class, 'archived plan cannot be the default');
});

it('refuses to leave no default plan', function (): void {
    $default = app(BillingCatalog::class)->defaultPlan();

    expect(fn () => app(SaveBillingPlanAction::class)->execute(planData(['key' => $default->key, 'isDefault' => false]), $default))
        ->toThrow(InvalidBillingCatalogException::class, 'must be the default');

    expect($default->refresh()->is_default)->toBeTrue();
});

it('refuses a negative price', function (): void {
    expect(fn () => app(SaveBillingPlanAction::class)->execute(planData(['yearlyPriceCents' => -1])))
        ->toThrow(InvalidBillingCatalogException::class, 'cannot be negative');
});
