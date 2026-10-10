<?php

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\PasswordSetup;
use Domain\Billing\Actions\Subscriptions\GrantSubscriptionAction;
use Domain\Billing\Listeners\HandleStripeWebhook;
use Domain\Billing\Models\UserAddon;
use Domain\Billing\Models\UserCredit;
use Domain\Billing\Notifications\FounderWelcomeNotification;
use Domain\Billing\Notifications\TrialWelcomeNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Laravel\Cashier\Events\WebhookReceived;
use Mockery as m;
use Stripe\Customer;
use Stripe\Exception\ApiConnectionException;
use Stripe\StripeClient;
use Stripe\Subscription as StripeSubscription;

afterEach(function (): void {
    m::close();
});

function makeSubscriptionCyclePayload(string $eventId, string $customerId, string $invoiceId = 'in_test_001'): array
{
    return [
        'id' => $eventId,
        'type' => 'invoice.payment_succeeded',
        'data' => [
            'object' => [
                'id' => $invoiceId,
                'customer' => $customerId,
                'billing_reason' => 'subscription_cycle',
                'subscription_details' => [
                    'metadata' => [
                        'to_package' => 'starter',
                        'is_yearly' => 'false',
                    ],
                ],
            ],
        ],
    ];
}

function makeAddonCheckoutPayload(string $eventId, string $customerId, int $messages = 1000): array
{
    return [
        'id' => $eventId,
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test_'.$eventId,
                'customer' => $customerId,
                'metadata' => [
                    'type' => 'addon',
                    'id' => 'extra_message_credits',
                    'messages' => $messages,
                ],
            ],
        ],
    ];
}

it('grants credit only once when the same Stripe event is delivered twice', function (): void {
    $user = User::factory()->create();
    $user->billing()->create(['stripe_id' => 'cus_replay_test']);

    $payload = makeSubscriptionCyclePayload('evt_replay_test_1', 'cus_replay_test');

    $listener = app(HandleStripeWebhook::class);

    $listener->handle(new WebhookReceived($payload));
    $countAfterFirst = UserCredit::where('user_id', $user->id)->count();

    $listener->handle(new WebhookReceived($payload));
    $countAfterReplay = UserCredit::where('user_id', $user->id)->count();

    expect($countAfterFirst)->toBe(1)
        ->and($countAfterReplay)->toBe(1);
});

it('releases the idempotency key when a handler throws so a Stripe retry reprocesses the event', function (): void {
    // The claim is taken with Cache::add before the work runs; if a handler
    // then fails, the claim must be released — otherwise Stripe's retry of
    // the same evt_* would be skipped and the event permanently lost.
    $user = User::factory()->create();
    $user->billing()->create(['stripe_id' => 'cus_retry_release']);

    $payload = makeSubscriptionCyclePayload('evt_retry_release', 'cus_retry_release');

    $grant = m::mock(GrantSubscriptionAction::class);
    $grant->shouldReceive('execute')->once()->andThrow(new RuntimeException('transient failure'));
    app()->instance(GrantSubscriptionAction::class, $grant);

    $listener = app(HandleStripeWebhook::class);

    expect(fn () => $listener->handle(new WebhookReceived($payload)))
        ->toThrow(RuntimeException::class, 'transient failure');

    expect(Cache::has('stripe:event:evt_retry_release'))->toBeFalse()
        ->and(UserCredit::where('user_id', $user->id)->count())->toBe(0);

    // A second delivery of the SAME event id reprocesses through the real
    // action and grants the credit.
    app()->forgetInstance(GrantSubscriptionAction::class);

    $listener->handle(new WebhookReceived($payload));

    expect(UserCredit::where('user_id', $user->id)->count())->toBe(1)
        ->and(Cache::has('stripe:event:evt_retry_release'))->toBeTrue();
});

it('grants credit again for a different Stripe event id', function (): void {
    $user = User::factory()->create();
    $user->billing()->create(['stripe_id' => 'cus_replay_test_2']);

    $listener = app(HandleStripeWebhook::class);

    $listener->handle(new WebhookReceived(
        makeSubscriptionCyclePayload('evt_first', 'cus_replay_test_2', 'in_first')
    ));

    $listener->handle(new WebhookReceived(
        makeSubscriptionCyclePayload('evt_second', 'cus_replay_test_2', 'in_second')
    ));

    // Second distinct event expires the first credit and creates a new active one.
    expect(UserCredit::where('user_id', $user->id)->count())->toBe(2)
        ->and($user->fresh()->credit)->not->toBeNull();
});

it('applies starter-plan quota limits and message credits on subscription_cycle', function (): void {
    // Asserts the success-path side of the existing branch — not just that
    // the idempotency cache works, but that the user actually gets the
    // package's quota_limits.fixed values and the configured monthly
    // message count as a UserCredit.
    $user = User::factory()->create();
    $user->billing()->create(['stripe_id' => 'cus_cycle_starter']);

    app(HandleStripeWebhook::class)->handle(new WebhookReceived(
        makeSubscriptionCyclePayload('evt_cycle_starter', 'cus_cycle_starter')
    ));

    $quota = $user->fresh()->quota;

    // From the Starter plan's quota_limits.fixed (billing_plans)
    expect($quota->agents_limit)->toBe(2)
        ->and($quota->pages_limit)->toBe(2000)
        ->and($quota->minutes_limit)->toBe(500)
        ->and($quota->links_limit)->toBe(2000)
        ->and($quota->custom_domains_limit)->toBe(0)
        ->and($quota->workspace_users_limit)->toBe(5)
        ->and($quota->teams_limit)->toBe(1);

    // From the Starter plan's quota_limits.monthly.messages_limit
    $credit = UserCredit::where('user_id', $user->id)->latest('id')->first();
    expect($credit)->not->toBeNull()
        ->and($credit->messages)->toBe(3000);
});

it('records a UserAddon for an extra_message_credits checkout', function (): void {
    $user = User::factory()->create();
    $user->billing()->create(['stripe_id' => 'cus_addon_test']);

    app(HandleStripeWebhook::class)->handle(new WebhookReceived(
        makeAddonCheckoutPayload('evt_addon_test', 'cus_addon_test', messages: 2500)
    ));

    $addon = UserAddon::where('user_id', $user->id)->latest('id')->first();

    expect($addon)->not->toBeNull()
        ->and($addon->type)->toBe('extra_message_credits')
        ->and($addon->amount)->toBe(2500)
        ->and($addon->stripe_id)->toBe('cs_test_evt_addon_test')
        ->and($addon->started_at)->not->toBeNull()
        ->and($addon->ends_at)->not->toBeNull();
});

function makeTrialRegistrationPayload(string $eventId, string $customerId, string $package = 'starter'): array
{
    return [
        'id' => $eventId,
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test_'.$eventId,
                'customer' => $customerId,
                'subscription' => 'sub_test_'.$eventId,
                'metadata' => [
                    'registration_type' => 'trial_registration',
                    'package' => $package,
                    'is_yearly' => 'false',
                    'user_ip' => '203.0.113.9',
                ],
            ],
        ],
    ];
}

it('provisions a user when Stripe POSTs a trial checkout.session.completed to the webhook route', function (): void {
    // Regression guard for the listener-wiring gap: HandleStripeWebhook must be
    // registered against Cashier's WebhookReceived event. Unlike the tests above
    // (which call ->handle() directly), this drives the real
    // route → Cashier WebhookController → WebhookReceived → listener path, so an
    // unregistered listener leaves no user behind and fails the assertion.
    Notification::fake();

    // Cashier only applies its signature-verification middleware when a webhook
    // secret is configured; clearing it lets the unsigned test payload through.
    config(['cashier.webhook.secret' => null]);

    // The founder note is opt-in; turn it on so the assertion below covers it.
    config([
        'chatvia.founder.welcome_email' => true,
        'chatvia.founder.name' => 'Jane Founder',
        'chatvia.founder.email' => 'founder@example.com',
        'chatvia.brand.name' => 'Acme Chat',
    ]);

    // The trial branch fetches the customer and the subscription from Stripe;
    // stub the StripeClient that Cashier resolves from the container (same
    // pattern as UpdateBillingInformationActionTest).
    $customers = m::mock();
    $customers->shouldReceive('retrieve')
        ->with('cus_trial_routing')
        ->andReturn(Customer::constructFrom([
            'id' => 'cus_trial_routing',
            'email' => 'newcustomer@example.test',
            'name' => 'New Customer',
        ]));
    $subscriptions = m::mock();
    $subscriptions->shouldReceive('retrieve')
        ->andReturn(StripeSubscription::constructFrom([
            'id' => 'sub_test_evt_trial_routing',
            'status' => 'trialing',
            'trial_end' => 1917220000, // far-future, so onTrial() is true
            'metadata' => [],
            'items' => [
                'object' => 'list',
                'data' => [[
                    'id' => 'si_test_1',
                    'quantity' => 1,
                    'price' => ['id' => 'price_starter_monthly', 'product' => 'prod_starter'],
                ]],
            ],
        ]));
    $stripe = m::mock(StripeClient::class);
    $stripe->customers = $customers;
    $stripe->subscriptions = $subscriptions;
    app()->bind(StripeClient::class, fn () => $stripe);

    expect(User::where('email', 'newcustomer@example.test')->exists())->toBeFalse();

    $this->postJson('/stripe/webhook', makeTrialRegistrationPayload('evt_trial_routing', 'cus_trial_routing'))
        ->assertSuccessful();

    $user = User::where('email', 'newcustomer@example.test')->first();

    expect($user)->not->toBeNull()
        ->and($user->name)->toBe('New Customer')
        ->and($user->email_verified_at)->not->toBeNull()
        ->and($user->billing->stripe_id)->toBe('cus_trial_routing')
        // Denormalised plan label persisted by grantSubscription().
        ->and($user->billing->plan)->toBe('starter');

    // The Cashier subscription (+ item) is synced, so subscribed() is now true
    // — the part that was silently failing because the row never got created.
    $billing = $user->billing;
    $subscription = $billing->subscriptions()->first();

    expect($subscription)->not->toBeNull()
        ->and($subscription->stripe_id)->toBe('sub_test_evt_trial_routing')
        ->and($subscription->stripe_status)->toBe('trialing')
        ->and($subscription->stripe_price)->toBe('price_starter_monthly')
        ->and($subscription->type)->toBe('default')
        ->and($subscription->items()->count())->toBe(1)
        ->and($billing->subscribed())->toBeTrue();

    // The account is provisioned with a random password nobody knows: the
    // owner is flagged for first-time password setup, and the welcome email
    // must not carry any password.
    expect(app(PasswordSetup::class)->isRequired($user))->toBeTrue();

    Notification::assertSentTo($user, TrialWelcomeNotification::class, function (TrialWelcomeNotification $notification) use ($user): bool {
        $mail = $notification->toMail($user);
        $body = collect($mail->introLines)->merge($mail->outroLines)->implode(' ');

        return str_contains($body, 'we never send passwords by email')
            && str_contains($body, 'magic link')
            && ! str_contains($body, 'Password:');
    });

    // The founder's personal note goes out alongside the transactional email:
    // greeted by first name, naming the configured brand, with replies routed
    // to the founder's own inbox.
    Notification::assertSentTo($user, FounderWelcomeNotification::class, function (FounderWelcomeNotification $notification) use ($user): bool {
        $mail = $notification->toMail($user);
        $body = collect($mail->introLines)->merge($mail->outroLines)->implode(' ');

        return $mail->greeting === 'Hi New,'
            && str_contains($body, 'Thank you for starting your Acme Chat trial')
            && str_contains($body, 'use case')
            && $mail->replyTo[0][0] === 'founder@example.com';
    });
});

function configurePlanPrices(): void
{
    foreach (['starter', 'standard', 'pro'] as $plan) {
        updateBillingPlan($plan, [
            'stripe_monthly_price_id' => "price_{$plan}_monthly",
            'stripe_yearly_price_id' => "price_{$plan}_yearly",
        ]);
    }
}

/**
 * A renewal invoice in the older API shape: `subscription_details` at the top
 * level and `price.id` on each line.
 *
 * @param  array<string, mixed>  $metadata
 * @param  list<array<string, mixed>>  $lines
 */
function makeRenewalInvoicePayload(string $eventId, string $customerId, array $metadata, array $lines): array
{
    return [
        'id' => $eventId,
        'type' => 'invoice.payment_succeeded',
        'data' => [
            'object' => [
                'id' => 'in_'.$eventId,
                'customer' => $customerId,
                'billing_reason' => 'subscription_cycle',
                'subscription' => 'sub_'.$eventId,
                'subscription_details' => ['metadata' => $metadata],
                'lines' => ['object' => 'list', 'data' => $lines],
            ],
        ],
    ];
}

it('derives the renewed plan and yearly cycle from the invoice price, not the cleared metadata', function (): void {
    // After a scheduled plan change is applied, the listener clears
    // `to_package` / `is_yearly` on the Stripe subscription. The next renewal
    // must still grant the plan the customer pays for (here Pro yearly), not
    // starter quotas on a monthly cycle.
    configurePlanPrices();

    $user = User::factory()->create();
    $user->billing()->create(['stripe_id' => 'cus_renew_pro_yearly']);

    app(HandleStripeWebhook::class)->handle(new WebhookReceived(makeRenewalInvoicePayload(
        'evt_renew_pro_yearly',
        'cus_renew_pro_yearly',
        metadata: ['pending_change' => 'false', 'applied_at' => now()->subYear()->toISOString()],
        lines: [
            // A proration line carries the previous plan's price and must be ignored.
            ['id' => 'il_1', 'proration' => true, 'price' => ['id' => 'price_starter_monthly']],
            ['id' => 'il_2', 'proration' => false, 'type' => 'subscription', 'price' => ['id' => 'price_pro_yearly']],
        ],
    )));

    $user = $user->fresh();
    $credit = UserCredit::where('user_id', $user->id)->latest('id')->first();

    expect($user->billing->plan)->toBe('pro')
        ->and($user->quota->agents_limit)->toBe(20)
        ->and($credit->messages)->toBe(648000)
        ->and($credit->ends_at->greaterThan(now()->addMonths(11)))->toBeTrue();
});

it('reads the plan price from the newer invoice shape (parent.subscription_details + pricing)', function (): void {
    configurePlanPrices();

    $user = User::factory()->create();
    $user->billing()->create(['stripe_id' => 'cus_renew_basil']);

    app(HandleStripeWebhook::class)->handle(new WebhookReceived([
        'id' => 'evt_renew_basil',
        'type' => 'invoice.payment_succeeded',
        'data' => [
            'object' => [
                'id' => 'in_renew_basil',
                'customer' => 'cus_renew_basil',
                'billing_reason' => 'subscription_cycle',
                'parent' => [
                    'type' => 'subscription_details',
                    'subscription_details' => ['subscription' => 'sub_basil', 'metadata' => []],
                ],
                'lines' => ['object' => 'list', 'data' => [[
                    'id' => 'il_basil',
                    'parent' => ['type' => 'subscription_item_details', 'subscription_item_details' => ['proration' => false]],
                    'pricing' => ['type' => 'price_details', 'price_details' => ['price' => 'price_standard_monthly', 'product' => 'prod_standard']],
                ]]],
            ],
        ],
    ]));

    $credit = UserCredit::where('user_id', $user->id)->latest('id')->first();

    expect($user->fresh()->billing->plan)->toBe('standard')
        ->and($credit->messages)->toBe(15000);
});

it('falls back to the synced Cashier subscription price when the invoice carries no lines', function (): void {
    configurePlanPrices();

    $user = User::factory()->create();
    $billing = $user->billing()->create(['stripe_id' => 'cus_renew_local']);
    $billing->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_local_plan',
        'stripe_status' => 'active',
        'stripe_price' => 'price_standard_yearly',
        'quantity' => 1,
    ]);

    app(HandleStripeWebhook::class)->handle(new WebhookReceived(makeRenewalInvoicePayload(
        'evt_renew_local',
        'cus_renew_local',
        metadata: [],
        lines: [],
    )));

    $credit = UserCredit::where('user_id', $user->id)->latest('id')->first();

    expect($user->fresh()->billing->plan)->toBe('standard')
        ->and($credit->messages)->toBe(192000);
});

it('does not re-grant the plan when an add-on subscription renews', function (): void {
    // An extra-agents add-on renews on its own invoice. Granting the plan from
    // it would reset the cycle's message credits and overwrite the quotas.
    configurePlanPrices();
    updateBillingAddon('extra_agents', ['stripe_price_id' => 'price_extra_agent']);

    $user = User::factory()->create();
    $user->billing()->create(['stripe_id' => 'cus_addon_renewal']);

    app(HandleStripeWebhook::class)->handle(new WebhookReceived(makeRenewalInvoicePayload(
        'evt_addon_renewal',
        'cus_addon_renewal',
        metadata: ['addon' => 'extra_agents', 'type' => 'extra_agents', 'name' => 'extra_agents', 'quantity' => '3'],
        lines: [['id' => 'il_addon', 'proration' => false, 'price' => ['id' => 'price_extra_agent']]],
    )));

    expect(UserCredit::where('user_id', $user->id)->count())->toBe(0)
        ->and($user->fresh()->billing->plan)->toBeNull();
});

it('rethrows a Stripe failure while creating a trial account so Stripe retries the event', function (): void {
    Notification::fake();

    $customers = m::mock();
    $customers->shouldReceive('retrieve')
        ->once()
        ->andThrow(new ApiConnectionException('Could not connect to Stripe'));
    $stripe = m::mock(StripeClient::class);
    $stripe->customers = $customers;
    app()->bind(StripeClient::class, fn () => $stripe);

    $payload = makeTrialRegistrationPayload('evt_trial_transient', 'cus_trial_transient');

    expect(fn () => app(HandleStripeWebhook::class)->handle(new WebhookReceived($payload)))
        ->toThrow(ApiConnectionException::class);

    // The claim is released, so Stripe's retry of the same event is processed.
    expect(Cache::has('stripe:event:evt_trial_transient'))->toBeFalse()
        ->and(User::where('email', 'transient@example.test')->exists())->toBeFalse();

    $customers = m::mock();
    $customers->shouldReceive('retrieve')->andReturn(Customer::constructFrom([
        'id' => 'cus_trial_transient',
        'email' => 'transient@example.test',
        'name' => 'Transient Customer',
    ]));
    $subscriptions = m::mock();
    $subscriptions->shouldReceive('retrieve')->andReturn(StripeSubscription::constructFrom([
        'id' => 'sub_test_evt_trial_transient',
        'status' => 'trialing',
        'trial_end' => 1917220000,
        'metadata' => [],
        'items' => ['object' => 'list', 'data' => [[
            'id' => 'si_transient',
            'quantity' => 1,
            'price' => ['id' => 'price_starter_monthly', 'product' => 'prod_starter'],
        ]]],
    ]));
    $stripe = m::mock(StripeClient::class);
    $stripe->customers = $customers;
    $stripe->subscriptions = $subscriptions;
    app()->bind(StripeClient::class, fn () => $stripe);

    app(HandleStripeWebhook::class)->handle(new WebhookReceived($payload));

    $user = User::where('email', 'transient@example.test')->first();

    expect($user)->not->toBeNull()
        ->and($user->billing->subscriptions()->count())->toBe(1);
});

it('rethrows a Stripe failure while syncing the trial subscription and finishes on the retry', function (): void {
    Notification::fake();

    $customers = m::mock();
    $customers->shouldReceive('retrieve')->andReturn(Customer::constructFrom([
        'id' => 'cus_trial_sync_fail',
        'email' => 'syncfail@example.test',
        'name' => 'Sync Fail',
    ]));
    $subscriptions = m::mock();
    $subscriptions->shouldReceive('retrieve')
        ->once()
        ->andThrow(new ApiConnectionException('timeout'));
    $stripe = m::mock(StripeClient::class);
    $stripe->customers = $customers;
    $stripe->subscriptions = $subscriptions;
    app()->bind(StripeClient::class, fn () => $stripe);

    $payload = makeTrialRegistrationPayload('evt_trial_sync_fail', 'cus_trial_sync_fail');

    expect(fn () => app(HandleStripeWebhook::class)->handle(new WebhookReceived($payload)))
        ->toThrow(ApiConnectionException::class);

    $user = User::where('email', 'syncfail@example.test')->first();

    // The account exists but has no subscription or credits yet.
    expect($user)->not->toBeNull()
        ->and($user->billing->subscriptions()->count())->toBe(0)
        ->and(UserCredit::where('user_id', $user->id)->count())->toBe(0);

    $subscriptions = m::mock();
    $subscriptions->shouldReceive('retrieve')->andReturn(StripeSubscription::constructFrom([
        'id' => 'sub_test_evt_trial_sync_fail',
        'status' => 'trialing',
        'trial_end' => 1917220000,
        'metadata' => [],
        'items' => ['object' => 'list', 'data' => [[
            'id' => 'si_sync_fail',
            'quantity' => 1,
            'price' => ['id' => 'price_starter_monthly', 'product' => 'prod_starter'],
        ]]],
    ]));
    $stripe->subscriptions = $subscriptions;

    app(HandleStripeWebhook::class)->handle(new WebhookReceived($payload));

    expect($user->billing->subscriptions()->count())->toBe(1)
        ->and(UserCredit::where('user_id', $user->id)->count())->toBe(1)
        // The retry found the existing account: still exactly one user.
        ->and(User::where('email', 'syncfail@example.test')->count())->toBe(1);
});

it('grants an extra_message_credits purchase once even when the event is processed again', function (): void {
    $user = User::factory()->create();
    $user->billing()->create(['stripe_id' => 'cus_addon_twice']);

    $payload = makeAddonCheckoutPayload('evt_addon_twice', 'cus_addon_twice', messages: 1000);

    app(HandleStripeWebhook::class)->handle(new WebhookReceived($payload));

    // The idempotency claim expires after 7 days; a later redelivery of the
    // same checkout must not grant a second add-on.
    Cache::flush();

    app(HandleStripeWebhook::class)->handle(new WebhookReceived($payload));

    expect(UserAddon::where('user_id', $user->id)->where('stripe_id', 'cs_test_evt_addon_twice')->count())->toBe(1);
});

it('refuses to process a webhook when no signing secret is set outside local and testing', function (): void {
    $user = User::factory()->create();
    $user->billing()->create(['stripe_id' => 'cus_unsigned']);

    config(['cashier.webhook.secret' => null]);
    app()->detectEnvironment(fn (): string => 'staging');

    try {
        expect(fn () => app(HandleStripeWebhook::class)->handle(new WebhookReceived(
            makeSubscriptionCyclePayload('evt_unsigned', 'cus_unsigned')
        )))->toThrow(RuntimeException::class, 'Refusing to process an unsigned Stripe webhook');

        expect(UserCredit::where('user_id', $user->id)->count())->toBe(0);

        // With a secret configured (Cashier verifies the signature) the same
        // environment processes the event.
        config(['cashier.webhook.secret' => 'whsec_test']);

        app(HandleStripeWebhook::class)->handle(new WebhookReceived(
            makeSubscriptionCyclePayload('evt_unsigned', 'cus_unsigned')
        ));

        expect(UserCredit::where('user_id', $user->id)->count())->toBe(1);
    } finally {
        app()->detectEnvironment(fn (): string => 'testing');
    }
});
