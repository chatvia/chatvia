<?php

declare(strict_types=1);

use Domain\Billing\Actions\Trials\StartTrialCheckoutAction;
use Domain\Billing\Data\Stripe\CheckoutUrlsData;
use Domain\Billing\Data\Trials\StartTrialCheckoutData;
use Domain\Billing\Models\BillingPlan;
use Stripe\Checkout\Session;
use Stripe\StripeClient;

function trialCheckoutFor(string $package): StartTrialCheckoutData
{
    return new StartTrialCheckoutData(
        package: $package,
        yearly: false,
        userIp: '127.0.0.1',
        urls: new CheckoutUrlsData(successUrl: 'https://example.test/ok', cancelUrl: 'https://example.test/back'),
    );
}

it('throws InvalidArgumentException when the requested package is not configured', function (): void {
    // Negative path doesn't hit Stripe — the plan lookup runs first and bails.
    // The controller maps this to a redirect with a flash error.
    expect(fn () => app(StartTrialCheckoutAction::class)->execute(trialCheckoutFor('does-not-exist')))
        ->toThrow(InvalidArgumentException::class, 'Unknown billing package: does-not-exist');
});

it('does not start a trial on an archived plan', function (): void {
    $plan = BillingPlan::factory()->archived()->create();

    expect(fn () => app(StartTrialCheckoutAction::class)->execute(trialCheckoutFor($plan->key)))
        ->toThrow(InvalidArgumentException::class, "Unknown billing package: {$plan->key}");
});

it('does not start a trial on a contact-sales plan', function (): void {
    expect(fn () => app(StartTrialCheckoutAction::class)->execute(trialCheckoutFor('enterprise')))
        ->toThrow(InvalidArgumentException::class, 'Package is not self-serve: enterprise');
});

it('labels the trial as coming from the default plan', function (): void {
    updateBillingPlan('starter', ['is_default' => false]);
    updateBillingPlan('standard', ['is_default' => true, 'stripe_monthly_price_id' => 'price_trial_standard_m']);

    $sessions = Mockery::mock();
    $sessions->shouldReceive('create')
        ->once()
        ->andReturnUsing(fn (array $params): Session => Session::constructFrom(['id' => 'cs_trial_default', 'params' => $params]));
    $stripe = Mockery::mock(StripeClient::class);
    $stripe->checkout = (object) ['sessions' => $sessions];
    app()->bind(StripeClient::class, fn () => $stripe);

    $session = app(StartTrialCheckoutAction::class)->execute(trialCheckoutFor('standard'));
    $params = $session->params->toArray();

    expect($params['line_items'][0]['price'])->toBe('price_trial_standard_m')
        ->and($params['subscription_data']['metadata']['from_package'])->toBe('standard')
        ->and($params['subscription_data']['metadata']['to_package'])->toBe('standard');
});
