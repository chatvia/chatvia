<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Subscriptions\SubscribeCustomDomainAction;
use Domain\Billing\Actions\Subscriptions\SubscribeExtraAgentsAction;
use Domain\Billing\Actions\Subscriptions\SubscribeMembersAction;
use Domain\Billing\Actions\Subscriptions\SubscribeRemoveBrandingAction;
use Domain\Billing\Data\SubscribeCustomDomainData;
use Domain\Billing\Data\SubscribeExtraAgentsData;
use Domain\Billing\Data\SubscribeMembersData;
use Domain\Billing\Exceptions\AddonNotConfiguredException;

/**
 * Buying an add-on whose Stripe price is not set throws the dedicated
 * AddonNotConfiguredException. The checkout controllers show only this
 * exception's message to the user, so it must not be a generic RuntimeException
 * (which would also match database errors). The Stripe checkout branch needs the
 * live API and is not covered here.
 */
it('throws AddonNotConfiguredException when the add-on price is missing', function (string $configKey, Closure $run): void {
    config([$configKey => null]);
    $user = User::factory()->create();

    expect(fn () => $run($user))->toThrow(AddonNotConfiguredException::class);
})->with([
    'custom domains' => [
        'billing.addons.custom_domains.stripe_price_id',
        fn (User $user) => app(SubscribeCustomDomainAction::class)->execute(new SubscribeCustomDomainData(user: $user, quantity: 1)),
    ],
    'extra agents' => [
        'billing.addons.extra_agents.stripe_price_id',
        fn (User $user) => app(SubscribeExtraAgentsAction::class)->execute(new SubscribeExtraAgentsData(user: $user, quantity: 1)),
    ],
    'members' => [
        'billing.addons.members.stripe_price_id',
        fn (User $user) => app(SubscribeMembersAction::class)->execute(new SubscribeMembersData(user: $user, quantity: 1)),
    ],
    'remove branding' => [
        'billing.addons.remove_branding.stripe_price_id',
        fn (User $user) => app(SubscribeRemoveBrandingAction::class)->execute($user),
    ],
]);
