<?php

declare(strict_types=1);

use App\Identity\Workspace\Models\User;
use Mockery as m;
use Stripe\StripeClient;
use Stripe\Subscription as StripeSubscription;
use Stripe\SubscriptionSchedule;

/**
 * A plan change finds the new plan by its key, so renaming a plan in the admin
 * panel never breaks it.
 */
afterEach(function (): void {
    m::close();
});

/**
 * A subscribed owner on Starter, and a fake Stripe that records the price of
 * the scheduled second phase.
 *
 * @return array{0: User, 1: object} the owner and a holder of the scheduled price
 */
function planChangeOwnerAndStripe(): array
{
    updateBillingPlan('starter', ['stripe_monthly_price_id' => 'price_key_starter_m']);
    updateBillingPlan('standard', ['name' => 'Growth', 'stripe_monthly_price_id' => 'price_key_standard_m']);

    $user = User::factory()->create();
    $user->billing()->firstOrCreate([])->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_change_by_key',
        'stripe_status' => 'active',
        'stripe_price' => 'price_key_starter_m',
        'quantity' => 1,
    ]);

    $scheduled = new class
    {
        public ?string $price = null;
    };

    $subscriptions = m::mock();
    $subscriptions->shouldReceive('retrieve')->andReturn(StripeSubscription::constructFrom([
        'id' => 'sub_change_by_key',
        'status' => 'active',
        'metadata' => [],
        'items' => ['object' => 'list', 'data' => [[
            'id' => 'si_change_by_key',
            'price' => ['id' => 'price_key_starter_m'],
            'current_period_start' => now()->subDays(5)->getTimestamp(),
            'current_period_end' => now()->addDays(25)->getTimestamp(),
        ]]],
    ]));
    $subscriptions->shouldReceive('update')->andReturn(StripeSubscription::constructFrom(['id' => 'sub_change_by_key']));

    $schedules = m::mock();
    $schedules->shouldReceive('create')->andReturn(SubscriptionSchedule::constructFrom(['id' => 'sched_change_by_key']));
    $schedules->shouldReceive('update')->andReturnUsing(function (string $id, array $params) use ($scheduled): SubscriptionSchedule {
        $scheduled->price = $params['phases'][1]['items'][0]['price'];

        return SubscriptionSchedule::constructFrom(['id' => $id]);
    });

    $stripe = m::mock(StripeClient::class);
    $stripe->subscriptions = $subscriptions;
    $stripe->subscriptionSchedules = $schedules;
    app()->bind(StripeClient::class, fn () => $stripe);

    return [$user, $scheduled];
}

it('schedules the change by plan key after the plan was renamed', function (): void {
    [$user, $scheduled] = planChangeOwnerAndStripe();

    $this->actingAs($user)
        ->post(route('billing.subscription.change'), ['package' => 'standard', 'is_yearly' => false])
        ->assertRedirect(route('billing.index'))
        ->assertSessionHas('success');

    expect($scheduled->price)->toBe('price_key_standard_m');
});

it('refuses a plan that is not sold through the plans page', function (): void {
    [$user, $scheduled] = planChangeOwnerAndStripe();

    $this->actingAs($user)
        ->post(route('billing.subscription.change'), ['package' => 'enterprise', 'is_yearly' => false])
        ->assertSessionHasErrors('package');

    expect($scheduled->price)->toBeNull();
});
