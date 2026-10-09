<?php

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\PasswordSetup;
use Domain\Billing\Actions\Trials\ResolveTrialCheckoutAccountAction;
use Domain\Billing\Exceptions\InvalidTrialCheckoutException;
use Mockery as m;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\StripeClient;

afterEach(function (): void {
    m::close();
});

function rtcSession(string $sessionId, string $customerId, string $status = 'complete'): void
{
    $sessions = m::mock();
    $sessions->shouldReceive('retrieve')->with($sessionId)->andReturn(CheckoutSession::constructFrom([
        'id' => $sessionId,
        'customer' => $customerId,
        'status' => $status,
        'created' => now()->subMinute()->getTimestamp(),
    ]));

    $checkout = new stdClass;
    $checkout->sessions = $sessions;
    $stripe = m::mock(StripeClient::class);
    $stripe->checkout = $checkout;
    app()->bind(StripeClient::class, fn () => $stripe);
}

it('allows one automatic sign-in for the account the checkout created', function (): void {
    $user = User::factory()->workspace()->create();
    $user->billing()->updateOrCreate([], ['stripe_id' => 'cus_rtc_new']);
    app(PasswordSetup::class)->flag($user);
    rtcSession('cs_rtc_new', 'cus_rtc_new');

    $first = app(ResolveTrialCheckoutAccountAction::class)->execute('cs_rtc_new');
    $second = app(ResolveTrialCheckoutAccountAction::class)->execute('cs_rtc_new');

    expect($first->user?->is($user))->toBeTrue()
        ->and($first->maySignInAutomatically)->toBeTrue()
        ->and($second->maySignInAutomatically)->toBeFalse();
});

it('never allows an automatic sign-in to an account that existed before the checkout', function (): void {
    $user = User::factory()->workspace()->create();
    $user->billing()->updateOrCreate([], ['stripe_id' => 'cus_rtc_existing']);
    rtcSession('cs_rtc_existing', 'cus_rtc_existing');

    $result = app(ResolveTrialCheckoutAccountAction::class)->execute('cs_rtc_existing');

    expect($result->user?->is($user))->toBeTrue()
        ->and($result->maySignInAutomatically)->toBeFalse();
});

it('rejects a checkout that is not complete', function (): void {
    rtcSession('cs_rtc_open', 'cus_rtc_open', status: 'open');

    expect(fn () => app(ResolveTrialCheckoutAccountAction::class)->execute('cs_rtc_open'))
        ->toThrow(InvalidTrialCheckoutException::class);
});
