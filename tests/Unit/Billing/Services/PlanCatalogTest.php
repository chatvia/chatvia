<?php

use Domain\Billing\Data\Subscriptions\PlanSelectionData;
use Domain\Billing\Enums\PlanFeature;
use Domain\Billing\Models\BillingPlan;
use Domain\Billing\Services\PlanCatalog;

/**
 * Price formatting must use the configured currency symbol everywhere,
 * including the zero-savings case of a package without a yearly discount.
 */
it('formats zero savings with the configured currency symbol', function (): void {
    config(['billing.currency_symbol' => '€']);
    BillingPlan::factory()->create([
        'key' => 'free_test',
        'name' => 'Free test',
        'monthly_price_cents' => 0,
        'yearly_price_cents' => 0,
        'stripe_monthly_price_id' => null,
        'stripe_yearly_price_id' => null,
    ]);

    expect(app(PlanCatalog::class)->package('free_test')['monthly_savings']['formatted'])->toBe('€0.00');
});

it('maps a Stripe price to its plan and cycle, and an unknown price to null', function (): void {
    updateBillingPlan('pro', [
        'stripe_monthly_price_id' => 'price_pro_m',
        'stripe_yearly_price_id' => 'price_pro_y',
    ]);

    $plans = app(PlanCatalog::class);

    expect($plans->planForPrice('price_pro_m'))->toEqual(new PlanSelectionData('pro', isYearly: false))
        ->and($plans->planForPrice('price_pro_y'))->toEqual(new PlanSelectionData('pro', isYearly: true))
        ->and($plans->planForPrice('price_addon'))->toBeNull()
        ->and($plans->planForPrice(null))->toBeNull();
});

it('resolves a subscription price to its package and falls back to the default plan for an unknown price', function (): void {
    updateBillingPlan('pro', [
        'stripe_monthly_price_id' => 'price_pro_m',
        'stripe_yearly_price_id' => 'price_pro_y',
    ]);

    $plans = app(PlanCatalog::class);
    $yearlyPro = $plans->packageForPrice('price_pro_y');

    expect($yearlyPro['id'])->toBe('pro')
        ->and($yearlyPro['is_yearly'])->toBeTrue()
        ->and($yearlyPro)->not->toHaveKey('stripe_yearly_price_id')
        ->and($plans->packageForPrice('price_archived')['id'])->toBe('starter');
});

it('resolves a legacy price of a plan with the interval it was billed at', function (): void {
    updateBillingPlan('standard', [
        'legacy_stripe_price_ids' => ['price_old_standard_m' => 'monthly', 'price_old_standard_y' => 'yearly'],
    ]);

    $plans = app(PlanCatalog::class);

    expect($plans->planForPrice('price_old_standard_m'))->toEqual(new PlanSelectionData('standard', isYearly: false))
        ->and($plans->packageForPrice('price_old_standard_y')['id'])->toBe('standard')
        ->and($plans->packageForPrice('price_old_standard_y')['is_yearly'])->toBeTrue();
});

it('hides an archived plan from the plan choice but still resolves it for its subscribers', function (): void {
    updateBillingPlan('standard', ['is_active' => false, 'stripe_monthly_price_id' => 'price_standard_m']);

    $plans = app(PlanCatalog::class);

    expect($plans->availablePackages()->keys()->all())->toBe(['starter', 'pro', 'enterprise'])
        ->and($plans->package('standard')['id'])->toBe('standard')
        ->and($plans->packageForPrice('price_standard_m')['id'])->toBe('standard');
});

/**
 * The plans page props keep the shape they had when the plans lived in
 * config/billing.php; the feature bullets are generated from the plan.
 */
it('sends the plans page every plan in order with its prices, features and capabilities', function (): void {
    config(['billing.currency_symbol' => '€']);

    $packages = app(PlanCatalog::class)->availablePackages();
    $starter = $packages['starter'];
    $enterprise = $packages['enterprise'];

    expect($packages->keys()->all())->toBe(['starter', 'standard', 'pro', 'enterprise'])
        ->and($starter['name'])->toBe('Starter')
        ->and($starter['monthly_price'])->toBe(4900)
        ->and($starter['monthly_price_formatted'])->toBe('€49.00')
        ->and($starter['yearly_price_formatted'])->toBe('€468.00')
        ->and($starter['monthly_savings'])->toBe(['amount' => 12000, 'percentage' => 20, 'formatted' => '€120.00'])
        ->and($starter['features']->monthly->first()->toArray())->toBe(['key' => 'messages_per_month', 'params' => ['count' => 3000], 'text' => null])
        ->and($starter['features']->yearly->first()->toArray())->toBe(['key' => 'messages_per_year', 'params' => ['count' => 39000], 'text' => null])
        ->and($starter['capabilities']['retentionDays'])->toBe(90)
        ->and($starter['capabilities']['sentiment'])->toBeFalse()
        ->and($packages['standard']['is_popular'])->toBeTrue()
        ->and($packages['pro']['capabilities']['siemWebhook'])->toBeTrue()
        ->and($enterprise['contact_sales'])->toBeTrue()
        ->and($enterprise['features']->monthly->last()->key)->toBe(PlanFeature::CustomContract)
        ->and($enterprise['monthly_price_formatted'])->toBe('€0.00');
});

it('lists the icon of every plan for the plan cards', function (): void {
    expect(app(PlanCatalog::class)->packageIcons())->toBe([
        'starter' => 'zap',
        'standard' => 'star',
        'pro' => 'crown',
        'enterprise' => 'building',
    ]);
});

it('falls back to the default plan limits for an account with no quota row', function (): void {
    expect(app(PlanCatalog::class)->defaultFixedLimit('teams_limit'))->toBe(1)
        ->and(app(PlanCatalog::class)->defaultFixedLimit('no_such_limit', 7))->toBe(7);
});
