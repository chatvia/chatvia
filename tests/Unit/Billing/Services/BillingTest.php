<?php

use Domain\Billing\Services\Billing;

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

    expect(app(Billing::class)->getPackage('free_test')['monthly_savings']['formatted'])->toBe('€0.00');
});

it('maps a Stripe price to its plan and cycle, and an unknown price to null', function (): void {
    config([
        'billing.packages.pro.stripe_monthly_price_id' => 'price_pro_m',
        'billing.packages.pro.stripe_yearly_price_id' => 'price_pro_y',
    ]);

    $billing = app(Billing::class);

    expect($billing->planForPriceId('price_pro_m'))->toBe(['package' => 'pro', 'is_yearly' => false])
        ->and($billing->planForPriceId('price_pro_y'))->toBe(['package' => 'pro', 'is_yearly' => true])
        ->and($billing->planForPriceId('price_addon'))->toBeNull()
        ->and($billing->planForPriceId(null))->toBeNull();
});
