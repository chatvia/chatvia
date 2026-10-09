<?php

use Domain\Billing\Actions\Trials\StartTrialCheckoutAction;
use Domain\Billing\Data\Stripe\CheckoutUrlsData;
use Domain\Billing\Data\Trials\StartTrialCheckoutData;

it('throws InvalidArgumentException when the requested package is not configured', function (): void {
    // Negative path doesn't hit Stripe — config lookup runs first and bails.
    // The controller maps this to a redirect with a flash error.
    config(['billing.packages' => [
        'starter' => ['stripe_monthly_price_id' => 'price_test', 'stripe_yearly_price_id' => 'price_test_yr'],
    ]]);

    expect(fn () => app(StartTrialCheckoutAction::class)->execute(new StartTrialCheckoutData(
        package: 'does-not-exist',
        yearly: false,
        userIp: '127.0.0.1',
        urls: new CheckoutUrlsData(successUrl: 'https://example.test/ok', cancelUrl: 'https://example.test/back'),
    )))->toThrow(InvalidArgumentException::class, 'Unknown billing package: does-not-exist');
});
