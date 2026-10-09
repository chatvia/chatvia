<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Subscriptions\SubscribeUserAction;
use Domain\Billing\Data\Stripe\CheckoutUrlsData;
use Domain\Billing\Data\Subscriptions\SubscribeData;

it('returns null when the requested package is not configured', function (): void {
    $user = User::factory()->create();

    $result = app(SubscribeUserAction::class)->execute(new SubscribeData(
        user: $user,
        package: 'does-not-exist-package-name',
        yearly: false,
        urls: new CheckoutUrlsData(successUrl: 'https://example.test/ok', cancelUrl: 'https://example.test/back'),
    ));

    expect($result)->toBeNull();
});
