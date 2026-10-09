<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Subscriptions\SchedulePlanChangeAction;
use Domain\Billing\Data\Subscriptions\SchedulePlanChangeData;

it('returns false when the user has no active subscription', function (): void {
    $user = User::factory()->create();

    $result = app(SchedulePlanChangeAction::class)->execute(new SchedulePlanChangeData(
        user: $user,
        package: 'starter',
        isYearly: false,
    ));

    expect($result)->toBeFalse();
});

it('returns false for a chatter with no billing row', function (): void {
    $workspace = User::factory()->workspace()->create();
    $chatter = User::factory()->chatter($workspace)->create();

    $result = app(SchedulePlanChangeAction::class)->execute(new SchedulePlanChangeData(
        user: $chatter,
        package: 'starter',
        isYearly: false,
    ));

    expect($result)->toBeFalse();
});
