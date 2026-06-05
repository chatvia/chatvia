<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Subscriptions\CancelSubscriptionAction;
use Illuminate\Support\Facades\DB;

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
    // workspace owner's billing (regression guard for any effectiveUser slip).
    expect(subscriptionsFor($workspace))->toBe($workspaceSubsBefore)
        ->and(subscriptionsFor($chatter))->toBe($chatterSubsBefore);
});
