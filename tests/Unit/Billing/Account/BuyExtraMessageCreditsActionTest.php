<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Account\BuyExtraMessageCreditsAction;
use Domain\Billing\Data\Account\BuyExtraMessageCreditsData;
use Domain\Billing\Data\Stripe\CheckoutUrlsData;
use Domain\Billing\Models\UserBilling;
use Laravel\Cashier\Checkout;
use Mockery as m;

afterEach(function (): void {
    m::close();
});

it('opens a checkout for the packs with the return URLs it was given and the pack metadata', function (): void {
    config([
        'billing.addons.extra_message_credits.stripe_price_id' => 'price_extra_1000',
        'billing.addons.extra_message_credits.unit_messages' => 1000,
    ]);

    $user = User::factory()->workspace()->create();
    $checkout = m::mock(Checkout::class);
    $billing = m::mock(UserBilling::class);
    $billing->shouldReceive('checkout')
        ->once()
        ->with(['price_extra_1000' => 3], m::on(fn (array $session): bool => $session['success_url'] === 'https://example.test/ok'
            && $session['cancel_url'] === 'https://example.test/back'
            && $session['metadata']['id'] === 'extra_message_credits'
            && $session['metadata']['type'] === 'addon'
            && $session['metadata']['messages'] === 3000))
        ->andReturn($checkout);
    $user->setRelation('billing', $billing);

    $result = app(BuyExtraMessageCreditsAction::class)->execute(new BuyExtraMessageCreditsData(
        user: $user,
        quantity: 3,
        urls: new CheckoutUrlsData(successUrl: 'https://example.test/ok', cancelUrl: 'https://example.test/back'),
    ));

    expect($result)->toBe($checkout);
});
