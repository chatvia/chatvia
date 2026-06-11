<?php

use App\Identity\Workspace\Actions\Account\DeleteUserAccountAction;
use App\Identity\Workspace\Models\User;
use Domain\Membership\Team\Models\Team;
use Domain\Membership\Team\Services\TeamService;
use Illuminate\Support\Facades\DB;

it('hard-deletes the user row', function (): void {
    $user = User::factory()->create();

    app(DeleteUserAccountAction::class)->execute($user);

    expect(User::whereKey($user->id)->exists())->toBeFalse();
});

it('does not touch unrelated users', function (): void {
    $target = User::factory()->create();
    $bystander = User::factory()->create();

    app(DeleteUserAccountAction::class)->execute($target);

    expect(User::whereKey($bystander->id)->exists())->toBeTrue();
});

it('tears down team memberships, owned teams, and workspace sub-users', function (): void {
    $owner = User::factory()->create();
    // Auto-provisioning is retired (the trial-checkout webhook owns it), so
    // seed the default team explicitly.
    $team = app(TeamService::class)->createDefaultTeam($owner);
    $role = $team->roles()->firstOrFail();

    // The owner is a member of their own team — this team_user row is exactly
    // what the team_user.team_id RESTRICT FK trips on if not cleared first.
    $team->members()->attach($owner->id, ['role_id' => $role->id]);

    $employee = User::factory()->employee($owner)->create();
    $team->members()->attach($employee->id, ['role_id' => $role->id]);

    $chatter = User::factory()->chatter($owner)->create();

    app(DeleteUserAccountAction::class)->execute($owner);

    expect(User::whereKey($owner->id)->exists())->toBeFalse()
        ->and(User::whereKey($employee->id)->exists())->toBeFalse()
        ->and(User::whereKey($chatter->id)->exists())->toBeFalse()
        ->and(Team::whereKey($team->id)->exists())->toBeFalse()
        ->and(DB::table('team_user')->where('team_id', $team->id)->exists())->toBeFalse();
});

it('tears down a workspace with multiple already-canceled subscriptions without calling Stripe', function (): void {
    $owner = User::factory()->create();

    // Factory users carry no UserBilling row (the trial-checkout webhook
    // provisions it in production) — create it explicitly.
    $billing = $owner->billing()->firstOrCreate([]);

    // Already-canceled subscriptions are skipped (no Stripe call) but must still
    // cascade-delete with user_billing — and a workspace can hold more than one.
    foreach (['sub_test_a', 'sub_test_b'] as $stripeId) {
        $billing->subscriptions()->create([
            'type' => $stripeId,
            'stripe_id' => $stripeId,
            'stripe_status' => 'canceled',
            'ends_at' => now()->subDay(),
        ]);
    }

    app(DeleteUserAccountAction::class)->execute($owner);

    expect(User::whereKey($owner->id)->exists())->toBeFalse()
        ->and(DB::table('subscriptions')->whereIn('stripe_id', ['sub_test_a', 'sub_test_b'])->exists())->toBeFalse();
});

it('deletes a workspace whose subscription is canceled but still within its trial window', function (): void {
    // Regression for the account-deletion 500: a subscription Stripe has already
    // canceled still reports subscribed()/onTrial() === true locally while
    // trial_ends_at is in the future. The teardown must NOT cancel it again —
    // Stripe rejects re-cancelling a closed subscription ("A canceled subscription
    // can only update its cancellation_details and metadata"). cancelSubscriptions()
    // skips it (ends_at is set) and nothing else may reach for Stripe; if anything
    // does, it hits $owner->stripe() and this test blows up. A clean delete proves
    // no second cancel is attempted.
    $owner = User::factory()->create();

    $billing = $owner->billing()->firstOrCreate([]);
    $billing->stripe_id = 'cus_test_trial';
    $billing->save();

    $subscription = $billing->subscriptions()->create([
        'type' => 'default',
        'stripe_id' => 'sub_test_trial',
        'stripe_status' => 'canceled',
        'ends_at' => now()->subHour(),        // already closed on Stripe
        'trial_ends_at' => now()->addDays(2), // …but the trial window hasn't lapsed
    ]);

    // The precondition that used to trip UserObserver::deleting(): canceled, yet
    // still "subscribed" because the trial is live.
    expect($subscription->canceled())->toBeTrue()
        ->and($owner->billing->subscribed())->toBeTrue();

    app(DeleteUserAccountAction::class)->execute($owner);

    expect(User::whereKey($owner->id)->exists())->toBeFalse()
        ->and(DB::table('subscriptions')->where('id', $subscription->id)->exists())->toBeFalse();
});

it('leaves other workspaces untouched while tearing one down', function (): void {
    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();
    $otherEmployee = User::factory()->employee($otherOwner)->create();

    app(DeleteUserAccountAction::class)->execute($owner);

    expect(User::whereKey($otherOwner->id)->exists())->toBeTrue()
        ->and(User::whereKey($otherEmployee->id)->exists())->toBeTrue();
});
