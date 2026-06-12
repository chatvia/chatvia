<?php

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\PasswordSetup;
use Mockery as m;
use Stripe\Checkout\Session as CheckoutSession;
use Stripe\StripeClient;

afterEach(function (): void {
    m::close();
});

/**
 * Stub the StripeClient that Cashier resolves from the container so the
 * controller's checkout-session lookup returns the given customer id
 * (same pattern as HandleStripeWebhookTest).
 */
function fakeStripeCheckoutSession(string $sessionId, string $customerId): void
{
    $sessions = m::mock();
    $sessions->shouldReceive('retrieve')
        ->with($sessionId)
        ->andReturn(CheckoutSession::constructFrom([
            'id' => $sessionId,
            'customer' => $customerId,
        ]));

    $checkout = new stdClass;
    $checkout->sessions = $sessions;

    $stripe = m::mock(StripeClient::class);
    $stripe->checkout = $checkout;

    app()->bind(StripeClient::class, fn () => $stripe);
}

it('logs in a freshly provisioned trial user and sends them to the set-password page', function (): void {
    $user = User::factory()->create();
    $user->billing()->create(['stripe_id' => 'cus_trial_flagged']);
    app(PasswordSetup::class)->flag($user);

    fakeStripeCheckoutSession('cs_test_flagged', 'cus_trial_flagged');

    $this->get(route('trial.success', ['session_id' => 'cs_test_flagged']))
        ->assertRedirect(route('password.setup'));

    $this->assertAuthenticatedAs($user);
});

it('sends a returning customer straight to the billing success page', function (): void {
    $user = User::factory()->create();
    $user->billing()->create(['stripe_id' => 'cus_trial_returning']);

    fakeStripeCheckoutSession('cs_test_returning', 'cus_trial_returning');

    $this->get(route('trial.success', ['session_id' => 'cs_test_returning']))
        ->assertRedirect(route('billing.success'));

    $this->assertAuthenticatedAs($user);
});

it('shows the processing page while the webhook has not provisioned the user yet', function (): void {
    fakeStripeCheckoutSession('cs_test_pending', 'cus_not_provisioned_yet');

    $this->get(route('trial.success', ['session_id' => 'cs_test_pending']))
        ->assertOk()
        ->assertViewIs('landing.payment-success');

    $this->assertGuest();
});

it('redirects home when the session id is missing', function (): void {
    $this->get(route('trial.success'))
        ->assertRedirect(route('home'));

    $this->assertGuest();
});
