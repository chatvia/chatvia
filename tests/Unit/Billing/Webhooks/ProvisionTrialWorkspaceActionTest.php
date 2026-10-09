<?php

use App\Identity\Workspace\Models\User;
use App\Identity\Workspace\Services\PasswordSetup;
use Domain\Billing\Actions\Webhooks\ProvisionTrialWorkspaceAction;
use Domain\Billing\Data\Stripe\CompletedCheckoutData;
use Domain\Billing\Data\Stripe\StripeMetadata;
use Domain\Billing\Events\TrialWorkspaceProvisioned;
use Domain\Billing\Exceptions\TrialCheckoutEmailInUseException;
use Domain\Billing\Listeners\SendTrialWelcomeEmails;
use Domain\Billing\Models\UserCredit;
use Domain\Billing\Notifications\TrialWelcomeNotification;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Exceptions;
use Illuminate\Support\Facades\Notification;
use Mockery as m;
use Stripe\Customer;
use Stripe\StripeClient;
use Stripe\Subscription as StripeSubscription;

afterEach(function (): void {
    m::close();
});

function ptwStripe(string $customerId, string $email, ?string $subscriptionId = null): void
{
    $customers = m::mock();
    $customers->shouldReceive('retrieve')->with($customerId)->andReturn(Customer::constructFrom([
        'id' => $customerId,
        'email' => $email,
        'name' => 'Trial Owner',
        'address' => ['country' => 'de'],
    ]));

    $subscriptions = m::mock();
    $subscriptions->shouldReceive('retrieve')->andReturn(StripeSubscription::constructFrom([
        'id' => $subscriptionId ?? 'sub_unused',
        'status' => 'trialing',
        'trial_end' => now()->addDays(7)->getTimestamp(),
        'metadata' => [],
        'items' => ['object' => 'list', 'data' => [[
            'id' => 'si_'.$customerId,
            'quantity' => 1,
            'price' => ['id' => 'price_starter_monthly', 'product' => 'prod_starter'],
        ]]],
    ]));

    $stripe = m::mock(StripeClient::class);
    $stripe->customers = $customers;
    $stripe->subscriptions = $subscriptions;
    app()->bind(StripeClient::class, fn () => $stripe);
}

function ptwCheckout(string $customerId, ?string $subscriptionId = 'sub_trial'): CompletedCheckoutData
{
    return new CompletedCheckoutData(
        sessionId: 'cs_'.$customerId,
        customerId: $customerId,
        subscriptionId: $subscriptionId,
        metadata: new StripeMetadata([
            StripeMetadata::REGISTRATION_TYPE => 'trial_registration',
            StripeMetadata::PACKAGE => 'starter',
            StripeMetadata::IS_YEARLY => 'false',
            StripeMetadata::USER_IP => '203.0.113.7',
        ]),
    );
}

it('creates a workspace owner for a new customer, flags the password setup and grants the plan', function (): void {
    Event::fake([TrialWorkspaceProvisioned::class]);
    ptwStripe('cus_new_owner', 'new-owner@example.test', 'sub_trial');

    $user = app(ProvisionTrialWorkspaceAction::class)->execute(ptwCheckout('cus_new_owner'));

    expect($user->email)->toBe('new-owner@example.test')
        ->and($user->isWorkspace())->toBeTrue()
        ->and($user->registered_ip)->toBe('203.0.113.7')
        ->and($user->registered_country_code)->toBe('DE')
        ->and($user->billing->stripe_id)->toBe('cus_new_owner')
        ->and($user->billing->subscriptions()->where('stripe_id', 'sub_trial')->exists())->toBeTrue()
        ->and($user->ownedTeams()->count())->toBe(1)
        ->and(UserCredit::query()->where('user_id', $user->id)->count())->toBe(1)
        ->and(app(PasswordSetup::class)->isRequired($user))->toBeTrue();

    Event::assertDispatched(TrialWorkspaceProvisioned::class, fn (TrialWorkspaceProvisioned $event): bool => $event->user->is($user)
        && $event->package === 'starter');
});

it('links an existing account with the same email without welcoming it again', function (): void {
    Event::fake([TrialWorkspaceProvisioned::class]);
    $existing = User::factory()->workspace()->create(['email' => 'known@example.test']);
    ptwStripe('cus_known', 'known@example.test');

    $user = app(ProvisionTrialWorkspaceAction::class)->execute(ptwCheckout('cus_known', subscriptionId: null));

    expect($user->is($existing))->toBeTrue()
        ->and($existing->fresh()->billing->stripe_id)->toBe('cus_known')
        ->and(app(PasswordSetup::class)->isRequired($existing))->toBeFalse();

    Event::assertNotDispatched(TrialWorkspaceProvisioned::class);
});

it('does not relink or change the plan of an account that already belongs to another Stripe customer', function (): void {
    // Anyone can type any email into Stripe Checkout, so a guest trial paid
    // with a customer's email must not take over that customer's billing.
    Exceptions::fake();
    Event::fake([TrialWorkspaceProvisioned::class]);
    $victim = User::factory()->workspace()->create(['email' => 'paying@example.test']);
    $victim->billing()->updateOrCreate([], ['stripe_id' => 'cus_original']);
    ptwStripe('cus_stranger', 'paying@example.test', 'sub_stranger');
    $creditsBefore = UserCredit::query()->where('user_id', $victim->id)->count();

    $result = app(ProvisionTrialWorkspaceAction::class)->execute(ptwCheckout('cus_stranger', subscriptionId: 'sub_stranger'));

    expect($result)->toBeNull()
        ->and($victim->fresh()->billing->stripe_id)->toBe('cus_original')
        ->and($victim->fresh()->billing->subscriptions()->where('stripe_id', 'sub_stranger')->exists())->toBeFalse()
        ->and(UserCredit::query()->where('user_id', $victim->id)->count())->toBe($creditsBefore);

    Exceptions::assertReported(TrialCheckoutEmailInUseException::class);
    Event::assertNotDispatched(TrialWorkspaceProvisioned::class);
});

it('finds the account already linked to the Stripe customer without calling Stripe', function (): void {
    $linked = User::factory()->workspace()->create();
    $linked->billing()->updateOrCreate([], ['stripe_id' => 'cus_linked']);

    $stripe = m::mock(StripeClient::class);
    $stripe->customers = m::mock();
    $stripe->customers->shouldNotReceive('retrieve');
    app()->bind(StripeClient::class, fn () => $stripe);
    $usersBefore = User::query()->count();

    $user = app(ProvisionTrialWorkspaceAction::class)->execute(ptwCheckout('cus_linked', subscriptionId: null));

    expect($user->is($linked))->toBeTrue()
        ->and(User::query()->count())->toBe($usersBefore);
});

it('reports a failed welcome email instead of failing the webhook', function (): void {
    Exceptions::fake();
    Notification::shouldReceive('send')->andThrow(new RuntimeException('mail server down'));
    $user = User::factory()->workspace()->create();

    app(SendTrialWelcomeEmails::class)->handle(new TrialWorkspaceProvisioned($user, 'starter'));

    Exceptions::assertReported(RuntimeException::class);
});

it('sends the trial welcome email to a new account', function (): void {
    Notification::fake();
    $user = User::factory()->workspace()->create();

    app(SendTrialWelcomeEmails::class)->handle(new TrialWorkspaceProvisioned($user, 'starter'));

    Notification::assertSentTo($user, TrialWelcomeNotification::class);
});
