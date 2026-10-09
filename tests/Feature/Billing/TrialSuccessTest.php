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
 * success page's checkout-session lookup returns the given session (same
 * pattern as HandleStripeWebhookTest).
 *
 * @param  array<string, mixed>  $overrides
 */
function fakeStripeCheckoutSession(string $sessionId, string $customerId, array $overrides = []): void
{
    $sessions = m::mock();
    $sessions->shouldReceive('retrieve')
        ->with($sessionId)
        ->andReturn(CheckoutSession::constructFrom([
            'id' => $sessionId,
            'customer' => $customerId,
            'status' => 'complete',
            'created' => now()->subMinutes(2)->getTimestamp(),
            ...$overrides,
        ]));

    $checkout = new stdClass;
    $checkout->sessions = $sessions;

    $stripe = m::mock(StripeClient::class);
    $stripe->checkout = $checkout;

    app()->bind(StripeClient::class, fn () => $stripe);
}

function trialAccount(string $customerId, bool $newAccount = true): User
{
    $user = User::factory()->create();
    $user->billing()->create(['stripe_id' => $customerId]);

    if ($newAccount) {
        app(PasswordSetup::class)->flag($user);
    }

    return $user;
}

it('logs in a freshly provisioned trial user and sends them to the set-password page', function (): void {
    $user = trialAccount('cus_trial_flagged');

    fakeStripeCheckoutSession('cs_test_flagged', 'cus_trial_flagged');

    $this->get(route('trial.success', ['session_id' => 'cs_test_flagged']))
        ->assertRedirect(route('password.setup'));

    $this->assertAuthenticatedAs($user);
});

it('never signs in an existing account from a checkout, it asks for a login instead', function (): void {
    // The checkout email is typed by whoever pays, so an existing account
    // matched by that email must not be opened from the success link.
    trialAccount('cus_trial_returning', newAccount: false);

    fakeStripeCheckoutSession('cs_test_returning', 'cus_trial_returning');

    $this->get(route('trial.success', ['session_id' => 'cs_test_returning']))
        ->assertRedirect(route('login'))
        ->assertSessionHas('status');

    $this->assertGuest();
});

it('sends a returning customer who is already signed in straight to the billing success page', function (): void {
    $user = trialAccount('cus_trial_signed_in', newAccount: false);

    fakeStripeCheckoutSession('cs_test_signed_in', 'cus_trial_signed_in');

    $this->actingAs($user)
        ->get(route('trial.success', ['session_id' => 'cs_test_signed_in']))
        ->assertRedirect(route('billing.success'));
});

it('sends an account with two-factor authentication to the challenge instead of signing it in', function (): void {
    $user = trialAccount('cus_trial_2fa');
    $user->forceFill([
        'two_factor_secret' => encrypt('secret'),
        'two_factor_confirmed_at' => now(),
    ])->save();

    fakeStripeCheckoutSession('cs_test_2fa', 'cus_trial_2fa');

    $this->get(route('trial.success', ['session_id' => 'cs_test_2fa']))
        ->assertRedirect(route('two-factor.login'))
        ->assertSessionHas('login.id', $user->id);

    $this->assertGuest();
});

it('signs in only once per checkout session', function (): void {
    trialAccount('cus_trial_once');

    fakeStripeCheckoutSession('cs_test_once', 'cus_trial_once');

    $this->get(route('trial.success', ['session_id' => 'cs_test_once']))
        ->assertRedirect(route('password.setup'));

    auth()->guard('web')->logout();

    $this->get(route('trial.success', ['session_id' => 'cs_test_once']))
        ->assertRedirect(route('login'));

    $this->assertGuest();
});

it('refuses a checkout that is not complete or is too old', function (array $overrides): void {
    trialAccount('cus_trial_stale');

    fakeStripeCheckoutSession('cs_test_stale', 'cus_trial_stale', $overrides);

    $this->get(route('trial.success', ['session_id' => 'cs_test_stale']))
        ->assertRedirect(route('home'));

    $this->assertGuest();
})->with([
    'still open' => [['status' => 'open']],
    'expired' => [['status' => 'expired']],
    'two days old' => [['created' => now()->subDays(2)->getTimestamp()]],
]);

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
