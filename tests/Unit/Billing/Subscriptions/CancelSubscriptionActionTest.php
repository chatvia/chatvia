<?php

use App\Auth\Models\User;
use Domain\Billing\Actions\Subscriptions\CancelSubscriptionAction;

it('runs the cancel path without error when the user has no subscription', function (): void {
    // No subscription -> the early branch is skipped, then cancelSubscription()
    // walks to the workspace owner's billing->subscription() which is null and
    // resolves to a no-op via null-safe chaining.
    $user = User::factory()->create();

    app(CancelSubscriptionAction::class)->execute($user);

    expect(true)->toBeTrue();
});

it('runs the cancel path without error for a chatter with no billing row', function (): void {
    $workspace = User::factory()->workspace()->create();
    $chatter = User::factory()->chatter($workspace)->create();

    app(CancelSubscriptionAction::class)->execute($chatter);

    expect(true)->toBeTrue();
});
