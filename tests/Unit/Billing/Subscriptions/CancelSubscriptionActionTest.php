<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Subscriptions\CancelSubscriptionAction;
use Illuminate\Support\Facades\DB;
use Stripe\StripeClient;
use Stripe\Subscription;

function subscriptionsFor(User $user): int
{
    return DB::table('subscriptions')
        ->join('user_billing', 'subscriptions.user_billing_id', '=', 'user_billing.id')
        ->where('user_billing.user_id', $user->id)
        ->count();
}

it('is a no-op for a user with no subscription — no Cashier subscription rows are inserted or mutated', function (): void {
    // No subscription -> the metadata-mutation branch is skipped and
    // cancelSubscription() walks to the workspace owner's billing->subscription()
    // which is null, so nothing is written.
    $user = User::factory()->create();

    expect(subscriptionsFor($user))->toBe(0);

    app(CancelSubscriptionAction::class)->execute($user);

    // Concrete state assertion: still zero subscription rows after cancel.
    // The previous vacuous `expect(true)->toBeTrue()` would have passed even
    // if the action accidentally inserted rows; this would not.
    expect(subscriptionsFor($user))->toBe(0);
});

it('is a no-op for a chatter with no billing row — workspace owner is left untouched', function (): void {
    $workspace = User::factory()->workspace()->create();
    $chatter = User::factory()->chatter($workspace)->create();

    $workspaceSubsBefore = subscriptionsFor($workspace);
    $chatterSubsBefore = subscriptionsFor($chatter);

    app(CancelSubscriptionAction::class)->execute($chatter);

    // Concretely: cancelling for the chatter must not write rows under the
    // workspace owner's billing (regression guard for any workspace slip).
    expect(subscriptionsFor($workspace))->toBe($workspaceSubsBefore)
        ->and(subscriptionsFor($chatter))->toBe($chatterSubsBefore);
});

it('withdraws a pending plan change with Stripe\'s spelling of canceled_at and cancels at period end', function (): void {
    $user = User::factory()->workspace()->create();
    $user->billing()->firstOrCreate([])->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_cancel_pending',
        'stripe_status' => 'trialing',
        'stripe_price' => 'price_starter_monthly',
        'quantity' => 1,
        'trial_ends_at' => now()->addDays(5),
    ]);

    $updates = [];
    $subscriptions = Mockery::mock();
    $subscriptions->shouldReceive('retrieve')->andReturn(Subscription::constructFrom([
        'id' => 'sub_cancel_pending',
        'status' => 'trialing',
        'metadata' => ['pending_change' => 'true', 'to_package' => 'pro'],
    ]));
    $subscriptions->shouldReceive('update')->andReturnUsing(function (string $id, array $options) use (&$updates): Subscription {
        $updates[] = $options;

        return Subscription::constructFrom(['id' => $id, 'status' => 'trialing']);
    });
    $stripe = Mockery::mock(StripeClient::class);
    $stripe->subscriptions = $subscriptions;
    app()->bind(StripeClient::class, fn () => $stripe);

    app(CancelSubscriptionAction::class)->execute($user->fresh());

    expect($updates)->toHaveCount(2)
        ->and($updates[0]['metadata'])->toHaveKey('canceled_at')
        ->and($updates[0]['metadata'])->not->toHaveKey('cancelled_at')
        ->and($updates[0]['metadata']['pending_change'])->toBe('false')
        ->and($updates[1])->toBe(['cancel_at_period_end' => true])
        ->and($user->fresh()->billing->subscription()->ends_at)->not->toBeNull();
});
