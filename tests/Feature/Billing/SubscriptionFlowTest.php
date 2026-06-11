<?php

use App\Identity\Workspace\Models\User;
use Illuminate\Support\Carbon;
use Mockery as m;
use Stripe\Checkout\Session as StripeCheckoutSession;
use Stripe\StripeClient;
use Stripe\Subscription as StripeSubscription;
use Stripe\SubscriptionSchedule;

afterEach(function (): void {
    m::close();
});

/**
 * Bind a Mockery StripeClient into the container so every Cashier call
 * (`Cashier::stripe()` resolves `app(StripeClient::class)`) hits the fake.
 * Same seam as HandleStripeWebhookTest / UpdateBillingInformationActionTest.
 */
function subscriptionFlowStripeClient(): m\MockInterface
{
    $stripe = m::mock(StripeClient::class);
    app()->bind(StripeClient::class, fn () => $stripe);

    return $stripe;
}

/**
 * A workspace owner with an explicitly provisioned billing row — registration
 * is trial-checkout-only now (the Stripe webhook provisions), so the factory
 * deliberately does not create UserBilling.
 */
function subscriptionFlowOwner(string $stripeCustomerId): User
{
    $user = User::factory()->create();
    $user->billing()->firstOrCreate([]);
    $user->billing()->update(['stripe_id' => $stripeCustomerId]);

    return $user;
}

it('schedules a plan change on Stripe and redirects back with a success flash', function (): void {
    $this->travelTo(Carbon::parse('2026-06-11 12:00:00'));

    config([
        'billing.packages.starter.stripe_monthly_price_id' => 'price_flow_starter_m',
        'billing.packages.standard.stripe_monthly_price_id' => 'price_flow_standard_m',
        'billing.packages.standard.stripe_yearly_price_id' => 'price_flow_standard_y',
    ]);

    $periodStart = now()->subDays(20)->getTimestamp();
    $periodEnd = now()->addDays(10)->getTimestamp();

    $user = subscriptionFlowOwner('cus_flow_change');
    $user->billing->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_flow_change',
        'stripe_status' => 'active',
        'stripe_price' => 'price_flow_starter_m',
        'quantity' => 1,
    ]);

    $stripe = subscriptionFlowStripeClient();

    // The action reads the live subscription (current phase + metadata) ...
    $subscriptions = m::mock();
    $subscriptions->shouldReceive('retrieve')
        ->with('sub_flow_change', m::any())
        ->andReturn(StripeSubscription::constructFrom([
            'id' => 'sub_flow_change',
            'status' => 'active',
            'metadata' => [],
            'items' => [
                'object' => 'list',
                'data' => [[
                    'id' => 'si_flow_change',
                    'price' => ['id' => 'price_flow_starter_m'],
                    'current_period_start' => $periodStart,
                    'current_period_end' => $periodEnd,
                ]],
            ],
        ]));

    // ... then stamps the pending-change metadata onto the subscription.
    $capturedMetadata = null;
    $subscriptions->shouldReceive('update')
        ->once()
        ->andReturnUsing(function (string $id, array $params) use (&$capturedMetadata) {
            expect($id)->toBe('sub_flow_change');
            $capturedMetadata = $params['metadata'] ?? null;

            return StripeSubscription::constructFrom(['id' => $id, 'status' => 'active']);
        });

    // A schedule is created from the running subscription and given two
    // phases: current price until period end, new price afterwards.
    $capturedPhases = null;
    $subscriptionSchedules = m::mock();
    $subscriptionSchedules->shouldReceive('create')
        ->once()
        ->with(['from_subscription' => 'sub_flow_change'])
        ->andReturn(SubscriptionSchedule::constructFrom(['id' => 'sched_flow_change']));
    $subscriptionSchedules->shouldReceive('update')
        ->once()
        ->andReturnUsing(function (string $id, array $params) use (&$capturedPhases) {
            expect($id)->toBe('sched_flow_change');
            $capturedPhases = $params['phases'] ?? null;

            return SubscriptionSchedule::constructFrom(['id' => $id]);
        });

    $stripe->subscriptions = $subscriptions;
    $stripe->subscriptionSchedules = $subscriptionSchedules;

    $response = $this->actingAs($user)->post(route('billing.subscription.change'), [
        'package' => 'standard',
        'is_yearly' => true,
    ]);

    $response->assertRedirect(route('billing.index'));
    $response->assertSessionHas('success');
    $response->assertSessionMissing('error');

    // Phase 1 keeps the current price until the period ends; phase 2 starts
    // the new (yearly Standard) price exactly at period end.
    expect($capturedPhases)->toHaveCount(2)
        ->and($capturedPhases[0]['items'][0]['price'])->toBe('price_flow_starter_m')
        ->and($capturedPhases[0]['end_date'])->toBe($periodEnd)
        ->and($capturedPhases[1]['items'][0]['price'])->toBe('price_flow_standard_y')
        ->and($capturedPhases[1]['start_date'])->toBe($periodEnd);

    // The subscription metadata is the contract the renewal webhook reads.
    expect($capturedMetadata)->not->toBeNull()
        ->and($capturedMetadata['pending_change'])->toBe('true')
        ->and($capturedMetadata['from_package'])->toBe('starter')
        ->and($capturedMetadata['to_package'])->toBe('standard')
        ->and($capturedMetadata['new_price_id'])->toBe('price_flow_standard_y')
        ->and($capturedMetadata['is_yearly'])->toBe('true')
        ->and($capturedMetadata['schedule_id'])->toBe('sched_flow_change');
});

it('redirects back with an error flash when the owner has no active subscription to change', function (): void {
    // No Stripe mock needed: the action bails before any Stripe call when
    // there is nothing to change.
    $user = subscriptionFlowOwner('cus_flow_nosub');

    $response = $this->actingAs($user)->post(route('billing.subscription.change'), [
        'package' => 'standard',
        'is_yearly' => false,
    ]);

    $response->assertRedirect(route('billing.index'));
    $response->assertSessionHas('error');
    $response->assertSessionMissing('success');
});

it('cancels the subscription into a grace period that ends with the current cycle', function (): void {
    $this->travelTo(Carbon::parse('2026-06-11 12:00:00'));

    config(['billing.packages.starter.stripe_monthly_price_id' => 'price_flow_starter_m']);

    $trialEndsAt = now()->addDays(5);

    $user = subscriptionFlowOwner('cus_flow_cancel');
    $subscription = $user->billing->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_flow_cancel',
        'stripe_status' => 'trialing',
        'stripe_price' => 'price_flow_starter_m',
        'quantity' => 1,
        'trial_ends_at' => $trialEndsAt,
    ]);

    $stripe = subscriptionFlowStripeClient();

    $subscriptions = m::mock();
    // The action inspects live metadata first (released schedules etc.) —
    // none pending here, so it goes straight to the cancel.
    $subscriptions->shouldReceive('retrieve')
        ->with('sub_flow_cancel', m::any())
        ->andReturn(StripeSubscription::constructFrom([
            'id' => 'sub_flow_cancel',
            'status' => 'trialing',
            'metadata' => [],
        ]));
    // Cashier cancels at period end — never immediately — so access survives
    // until the cycle the customer already paid for (or their trial) runs out.
    $subscriptions->shouldReceive('update')
        ->once()
        ->with('sub_flow_cancel', ['cancel_at_period_end' => true])
        ->andReturn(StripeSubscription::constructFrom([
            'id' => 'sub_flow_cancel',
            'status' => 'trialing',
            'cancel_at_period_end' => true,
        ]));

    $stripe->subscriptions = $subscriptions;

    $this->actingAs($user)
        ->post(route('billing.cancel'))
        ->assertSuccessful();

    $subscription->refresh();

    expect($subscription->ends_at)->not->toBeNull()
        ->and($subscription->ends_at->equalTo($trialEndsAt))->toBeTrue()
        ->and($subscription->onGracePeriod())->toBeTrue()
        // Still valid until the grace period lapses — cancellation is not
        // an instant cutoff.
        ->and($subscription->valid())->toBeTrue();
});

it('creates a Stripe Checkout session for a trial signup and redirects the guest to it', function (): void {
    config([
        'billing.packages.standard.stripe_monthly_price_id' => 'price_flow_trial_standard_m',
        'billing.trial.days' => 7,
    ]);

    $stripe = subscriptionFlowStripeClient();

    $capturedPayload = null;
    $sessions = m::mock();
    $sessions->shouldReceive('create')
        ->once()
        ->andReturnUsing(function (array $params) use (&$capturedPayload) {
            $capturedPayload = $params;

            return StripeCheckoutSession::constructFrom([
                'id' => 'cs_flow_trial',
                'url' => 'https://checkout.stripe.com/c/pay/cs_flow_trial',
            ]);
        });
    $checkout = m::mock();
    $checkout->sessions = $sessions;
    $stripe->checkout = $checkout;

    // Guest route — the trial funnel runs before any account exists.
    $response = $this->post(route('trial.checkout'), [
        'package' => 'standard',
        'yearly' => false,
    ]);

    $response->assertRedirect('https://checkout.stripe.com/c/pay/cs_flow_trial');

    expect($capturedPayload)->not->toBeNull()
        ->and($capturedPayload['mode'])->toBe('subscription')
        ->and($capturedPayload['line_items'])->toBe([
            ['price' => 'price_flow_trial_standard_m', 'quantity' => 1],
        ]);

    // Session metadata is what `checkout.session.completed` provisioning
    // reads (HandleStripeWebhook) — the contract that turns a paid checkout
    // into a workspace account.
    expect($capturedPayload['metadata']['registration_type'])->toBe('trial_registration')
        ->and($capturedPayload['metadata']['package'])->toBe('standard')
        ->and($capturedPayload['metadata']['is_yearly'])->toBe('false');

    // Subscription metadata is what the renewal-cycle webhooks read later.
    expect($capturedPayload['subscription_data']['trial_period_days'])->toBe(7)
        ->and($capturedPayload['subscription_data']['metadata']['to_package'])->toBe('standard')
        ->and($capturedPayload['subscription_data']['metadata']['registration_type'])->toBe('trial_registration');
});

it('rejects a trial checkout for an unknown package without touching Stripe', function (): void {
    // Validation stops in:starter,standard,pro before the action runs, so a
    // bogus package can never reach the (unbound, would-be-real) Stripe client.
    $this->from(route('home'))
        ->post(route('trial.checkout'), [
            'package' => 'enterprise',
            'yearly' => false,
        ])
        ->assertRedirect(route('home'))
        ->assertSessionHasErrors('package');
});
