<?php

use App\Auth\Models\User;
use Domain\Billing\Actions\Subscriptions\CancelScheduledPlanChangeAction;

it('returns false when the user has no subscription', function (): void {
    $user = User::factory()->create();

    $result = app(CancelScheduledPlanChangeAction::class)->execute($user);

    expect($result)->toBeFalse();
});

it('returns false for a chatter that has no billing row at all', function (): void {
    $workspace = User::factory()->workspace()->create();
    $chatter = User::factory()->chatter($workspace)->create();

    $result = app(CancelScheduledPlanChangeAction::class)->execute($chatter);

    expect($result)->toBeFalse();
});
