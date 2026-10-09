<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Addons\SubscribeAddonAction;
use Domain\Billing\Data\Stripe\CheckoutUrlsData;
use Domain\Billing\Data\Subscriptions\SubscribeAddonData;
use Domain\Billing\Enums\Addon;
use Domain\Billing\Exceptions\AddonNotConfiguredException;

/**
 * Buying an add-on whose Stripe price is not set throws the dedicated
 * AddonNotConfiguredException. The checkout controllers show only this
 * exception's message to the user, so it must not be a generic RuntimeException
 * (which would also match database errors). The Stripe checkout branch needs the
 * live API and is not covered here.
 */
it('throws AddonNotConfiguredException when the add-on price is missing', function (Addon $addon, string $message): void {
    config(["billing.addons.{$addon->value}.stripe_price_id" => null]);
    $user = User::factory()->create();

    expect(fn () => app(SubscribeAddonAction::class)->execute(new SubscribeAddonData(
        user: $user,
        addon: $addon,
        quantity: 1,
        urls: new CheckoutUrlsData(successUrl: 'https://example.test/ok', cancelUrl: 'https://example.test/back'),
    )))->toThrow(AddonNotConfiguredException::class, $message);
})->with([
    'custom domains' => [Addon::CustomDomains, 'Custom Domain addon is not configured. Set STRIPE_CUSTOM_DOMAIN_PRICE_ID in your environment.'],
    'extra agents' => [Addon::ExtraAgents, 'Extra Agents addon is not configured. Set STRIPE_EXTRA_AGENT_PRICE_ID in your environment.'],
    'members' => [Addon::Members, 'Members addon is not configured. Set STRIPE_MEMBERS_PRICE_ID in your environment.'],
    'remove branding' => [Addon::RemoveBranding, 'Remove Branding addon is not configured. Set STRIPE_REMOVE_BRANDING_PRICE_ID in your environment.'],
]);

it('needs no checkout for a yes/no add-on the workspace already has', function (): void {
    config(['billing.addons.remove_branding.stripe_price_id' => 'price_remove_branding']);

    $user = User::factory()->workspace()->create();
    $user->billing()->firstOrCreate([])->subscriptions()->create([
        'type' => Addon::RemoveBranding->value,
        'stripe_id' => 'sub_remove_branding',
        'stripe_status' => 'active',
        'stripe_price' => 'price_remove_branding',
        'quantity' => 1,
    ]);

    $checkout = app(SubscribeAddonAction::class)->execute(new SubscribeAddonData(
        user: $user->fresh(),
        addon: Addon::RemoveBranding,
        quantity: 5,
        urls: new CheckoutUrlsData(successUrl: 'https://example.test/ok', cancelUrl: 'https://example.test/back'),
    ));

    expect($checkout)->toBeNull()
        ->and($user->fresh()->quota?->extra_agents_limit ?? 0)->toBe(0);
});
