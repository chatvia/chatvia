<?php

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\PasswordSetup;
use Domain\Billing\Actions\Subscriptions\GrantSubscriptionAction;
use Domain\Billing\Listeners\HandleStripeWebhook;
use Domain\Billing\Models\UserAddon;
use Domain\Billing\Models\UserCredit;
use Domain\Billing\Models\UserQuota;
use Domain\Billing\Notifications\FounderWelcomeNotification;
use Domain\Billing\Notifications\TrialWelcomeNotification;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Notification;
use Laravel\Cashier\Events\WebhookReceived;
use Mockery as m;
use Stripe\Customer;
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

function makeAddonSubscriptionCreatedPayload(string $eventId, string $customerId, string $addon, int $quantity): array
{
    return [
        'id' => $eventId,
        'type' => 'checkout.session.completed',
        'data' => [
            'object' => [
                'id' => 'cs_test_'.$eventId,
                'customer' => $customerId,
                'metadata' => [
                    'type' => 'addon_subscription',
                    'addon' => $addon,
                    'quantity' => $quantity,
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

    // From config/billing.php → packages.starter.quota_limits.fixed
    expect($quota->agents_limit)->toBe(2)
        ->and($quota->pages_limit)->toBe(2000)
        ->and($quota->minutes_limit)->toBe(500)
        ->and($quota->links_limit)->toBe(2000)
        ->and($quota->custom_domains_limit)->toBe(0)
        ->and($quota->workspace_users_limit)->toBe(5)
        ->and($quota->teams_limit)->toBe(1);

    // From config/billing.php → packages.starter.quota_limits.monthly.messages_limit
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

it('mirrors extra_agents addon quantity onto user_quotas.extra_agents_limit', function (): void {
    $user = User::factory()->create();
    $user->billing()->create(['stripe_id' => 'cus_extra_agents']);

    app(HandleStripeWebhook::class)->handle(new WebhookReceived(
        makeAddonSubscriptionCreatedPayload('evt_extra_agents', 'cus_extra_agents', addon: 'extra_agents', quantity: 5)
    ));

    expect($user->fresh()->quota->extra_agents_limit)->toBe(5);
});

it('ignores addon_subscription events for unknown addon names', function (): void {
    $user = User::factory()->create();
    $user->billing()->create(['stripe_id' => 'cus_unknown_addon']);
    UserQuota::firstOrCreate(
        ['user_id' => $user->id],
        config('billing.packages.starter.quota_limits.fixed', []),
    );
    $baselineExtraAgents = $user->fresh()->quota->extra_agents_limit;

    app(HandleStripeWebhook::class)->handle(new WebhookReceived(
        makeAddonSubscriptionCreatedPayload('evt_unknown_addon', 'cus_unknown_addon', addon: 'unrecognised-addon', quantity: 9)
    ));

    expect($user->fresh()->quota->extra_agents_limit)->toBe($baselineExtraAgents);
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
    // greeted by first name, covering the thanks / EU-AI / use-case beats, with
    // replies routed to the founder's own inbox.
    Notification::assertSentTo($user, FounderWelcomeNotification::class, function (FounderWelcomeNotification $notification) use ($user): bool {
        $mail = $notification->toMail($user);
        $body = collect($mail->introLines)->merge($mail->outroLines)->implode(' ');

        return $mail->greeting === 'Hi New,'
            && str_contains($body, 'Thank you for starting your Chatvia trial')
            && str_contains($body, 'EU-based AI')
            && str_contains($body, 'use case')
            && $mail->replyTo[0][0] === config('chatvia.founder.email');
    });
});

it('resets extra_agents_limit to 0 on customer.subscription.deleted', function (): void {
    $user = User::factory()->create();
    $user->billing()->create(['stripe_id' => 'cus_deleted_sub']);
    UserQuota::firstOrCreate(
        ['user_id' => $user->id],
        config('billing.packages.starter.quota_limits.fixed', []),
    );
    $user->quota()->update(['extra_agents_limit' => 4]);

    app(HandleStripeWebhook::class)->handle(new WebhookReceived([
        'id' => 'evt_subscription_deleted',
        'type' => 'customer.subscription.deleted',
        'data' => [
            'object' => [
                'id' => 'sub_test_deleted',
                'customer' => 'cus_deleted_sub',
                'metadata' => [
                    'type' => 'addon_subscription',
                    'addon' => 'extra_agents',
                ],
            ],
        ],
    ]));

    expect($user->fresh()->quota->extra_agents_limit)->toBe(0);
});
