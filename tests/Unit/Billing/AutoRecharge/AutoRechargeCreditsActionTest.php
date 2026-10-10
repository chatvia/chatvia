<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\AutoRecharge\AutoRechargeCreditsAction;
use Domain\Billing\Models\UserAddon;
use Domain\Billing\Models\UserBilling;
use Laravel\Cashier\Invoice;
use Mockery as m;

/**
 * AutoRechargeCreditsAction charges the default payment method off-session
 * (Cashier invoicePrice) and records the matching user_addons row keyed on
 * the Stripe invoice id — the idempotency anchor that makes a job re-run for
 * the same charge unable to grant credits twice. The Stripe seam is the
 * UserBilling Cashier call itself: a Mockery subclass stands in for the
 * billing row (the container-bound StripeClient pattern used by sibling
 * tests can't intercept Cashier's Invoice::pay()), while update() passes
 * through to the real row so the debounce write stays observable.
 */
afterEach(function (): void {
    m::close();
});

/**
 * @return array{0: User, 1: UserBilling}
 */
function arcaUser(): array
{
    $user = User::factory()->create();
    // Registration is trial-checkout-only; provision billing explicitly.
    $billing = $user->billing()->firstOrCreate([]);

    return [$user, $billing];
}

/**
 * Stand-in for the workspace's UserBilling row: invoicePrice is stubbed by
 * the caller, update() forwards to the real row.
 */
function arcaMockBilling(User $user, UserBilling $real): m\MockInterface
{
    $mock = m::mock(UserBilling::class);
    $mock->shouldReceive('update')
        ->andReturnUsing(fn (array $attributes): bool => $real->update($attributes));

    // accountBilling() resolves workspace()->billing — pre-load the relation
    // so the action receives the mock.
    $user->setRelation('billing', $mock);

    return $mock;
}

function arcaFakeInvoice(string $stripeInvoiceId): Invoice
{
    $invoice = m::mock(Invoice::class);
    $invoice->shouldReceive('asStripeInvoice')
        ->andReturn(Stripe\Invoice::constructFrom(['id' => $stripeInvoiceId]));

    return $invoice;
}

it('charges the price off-session and records the addon, reserving the debounce before the charge', function (): void {
    updateBillingAddon('auto_recharge_credits', [
        'stripe_price_id' => 'price_extra_1000',
        'unit_messages' => 1000,
    ]);

    [$user, $billing] = arcaUser();
    $mock = arcaMockBilling($user, $billing);

    $debounceAtChargeTime = null;
    $mock->shouldReceive('invoicePrice')
        ->once()
        ->with('price_extra_1000', 2, [], m::on(fn (array $options): bool => ($options['metadata']['id'] ?? null) === 'auto_recharge_credits'
            && ($options['metadata']['messages'] ?? null) === 2000))
        ->andReturnUsing(function () use ($billing, &$debounceAtChargeTime): Invoice {
            // Capture the committed debounce at the moment Stripe is hit: the
            // reservation must land BEFORE the charge so a killed worker
            // cannot re-charge the card on the next low-balance turn.
            $debounceAtChargeTime = $billing->fresh()->auto_recharge_last_triggered_at;

            return arcaFakeInvoice('in_test_recharge');
        });

    $addon = app(AutoRechargeCreditsAction::class)->execute($user, 2);

    expect($debounceAtChargeTime)->not->toBeNull()
        ->and($addon)->not->toBeNull()
        ->and($addon->stripe_id)->toBe('in_test_recharge')
        ->and($addon->type)->toBe('auto_recharge_credits')
        // 2 units x 1000 messages per unit.
        ->and($addon->amount)->toBe(2000)
        ->and(UserAddon::query()->where('user_id', $user->id)->count())->toBe(1);
});

it('is idempotent on the Stripe invoice id — a re-run for the same charge grants nothing extra', function (): void {
    updateBillingAddon('auto_recharge_credits', [
        'stripe_price_id' => 'price_extra_1000',
        'unit_messages' => 1000,
    ]);

    [$user, $billing] = arcaUser();
    $mock = arcaMockBilling($user, $billing);

    // Stripe returns the SAME invoice both times (e.g. job retry after a
    // timeout between charge and commit).
    $mock->shouldReceive('invoicePrice')
        ->twice()
        ->andReturn(arcaFakeInvoice('in_test_same_charge'));

    $first = app(AutoRechargeCreditsAction::class)->execute($user, 1);

    // execute() refreshes $user, dropping the mocked relation — re-pin it for
    // the retry.
    $user->setRelation('billing', $mock);
    $second = app(AutoRechargeCreditsAction::class)->execute($user, 1);

    expect($second->id)->toBe($first->id)
        ->and(UserAddon::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and($second->fresh()->amount)->toBe(1000);
});

it('returns null without charging or reserving the debounce when no price is configured', function (): void {
    updateBillingAddon('auto_recharge_credits', ['stripe_price_id' => null]);

    [$user, $billing] = arcaUser();

    $result = app(AutoRechargeCreditsAction::class)->execute($user, 1);

    expect($result)->toBeNull()
        ->and(UserAddon::query()->where('user_id', $user->id)->exists())->toBeFalse()
        ->and($billing->fresh()->auto_recharge_last_triggered_at)->toBeNull();
});
