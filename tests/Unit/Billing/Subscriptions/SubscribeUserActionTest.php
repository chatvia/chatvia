<?php

use App\Auth\Models\User;
use Domain\Billing\Actions\Subscriptions\SubscribeUserAction;
use Domain\Billing\Data\SubscribeData;

it('returns null when the requested package is not configured', function (): void {
    $user = User::factory()->create();

    $result = app(SubscribeUserAction::class)->execute(new SubscribeData(
        user: $user,
        package: 'does-not-exist-package-name',
        yearly: false,
    ));

    expect($result)->toBeNull();
});
