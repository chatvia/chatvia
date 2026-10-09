<?php

use Domain\Billing\Data\Subscriptions\PlanSelectionData;
use Domain\Billing\Services\PlanCatalog;

/**
 * Price formatting must use the configured currency symbol everywhere,
 * including the zero-savings case of a package without a yearly discount.
 */
it('formats zero savings with the configured currency symbol', function (): void {
    config([
        'billing.currency_symbol' => '€',
        'billing.packages.free_test' => [
            'name' => 'Free test',
            'monthly_price' => 0,
            'yearly_price' => 0,
            'stripe_monthly_price_id' => null,
            'stripe_yearly_price_id' => null,
        ],
    ]);

    expect(app(PlanCatalog::class)->package('free_test')['monthly_savings']['formatted'])->toBe('€0.00');
});

it('maps a Stripe price to its plan and cycle, and an unknown price to null', function (): void {
    config([
        'billing.packages.pro.stripe_monthly_price_id' => 'price_pro_m',
        'billing.packages.pro.stripe_yearly_price_id' => 'price_pro_y',
    ]);

    $plans = app(PlanCatalog::class);

    expect($plans->planForPrice('price_pro_m'))->toEqual(new PlanSelectionData('pro', isYearly: false))
        ->and($plans->planForPrice('price_pro_y'))->toEqual(new PlanSelectionData('pro', isYearly: true))
        ->and($plans->planForPrice('price_addon'))->toBeNull()
        ->and($plans->planForPrice(null))->toBeNull();
});

it('resolves a subscription price to its package and falls back to the default plan for an unknown price', function (): void {
    config([
        'billing.packages.pro.stripe_monthly_price_id' => 'price_pro_m',
        'billing.packages.pro.stripe_yearly_price_id' => 'price_pro_y',
    ]);

    $plans = app(PlanCatalog::class);
    $yearlyPro = $plans->packageForPrice('price_pro_y');

    expect($yearlyPro['id'])->toBe('pro')
        ->and($yearlyPro['is_yearly'])->toBeTrue()
        ->and($yearlyPro)->not->toHaveKey('stripe_yearly_price_id')
        ->and($plans->packageForPrice('price_archived')['id'])->toBe(config('billing.default_package'))
        ->and($plans->packageByName('pro')['id'])->toBe('pro');
});
