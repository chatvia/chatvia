<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Subscriptions\ResumeSubscriptionAction;

it('returns false when the user has no active subscription', function (): void {
    $user = User::factory()->create();

    $result = app(ResumeSubscriptionAction::class)->execute($user);

    expect($result)->toBeFalse();
});

it('returns false for a chatter that has no billing row', function (): void {
    $workspace = User::factory()->workspace()->create();
    $chatter = User::factory()->chatter($workspace)->create();

    $result = app(ResumeSubscriptionAction::class)->execute($chatter);

    expect($result)->toBeFalse();
});
