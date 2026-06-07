<?php

use App\Identity\Workspace\Models\User;
use Domain\Agent\Models\Agent;
use Illuminate\Support\Str;

/**
 * Gives a workspace the active `remove_branding` add-on by writing the Cashier
 * subscription row directly — no Stripe round-trip. `stripe_status=active` with
 * a null `ends_at` makes Cashier's subscribed()/valid() return true.
 */
function grantRemoveBranding(User $user): void
{
    $billing = $user->billing()->updateOrCreate([], ['stripe_id' => 'cus_'.Str::random(14)]);

    $billing->subscriptions()->create([
        'type' => 'remove_branding',
        'stripe_id' => 'sub_'.Str::random(20),
        'stripe_status' => 'active',
        'stripe_price' => 'price_test_remove_branding',
        'quantity' => 1,
    ]);
}

it('does not let a workspace hide branding without the add-on', function (): void {
    $user = User::factory()->create();

    expect($user->canHideBranding())->toBeFalse();
});

it('lets a workspace hide branding with an active remove_branding subscription', function (): void {
    $user = User::factory()->create();

    grantRemoveBranding($user);

    expect($user->fresh()->canHideBranding())->toBeTrue();
});

it('shows the Chatvia badge on an agent by default', function (): void {
    $agent = Agent::factory()->create();

    expect($agent->loadMissing('team.owner')->showsChatviaBranding())->toBeTrue();
});

it('hides the Chatvia badge on an agent when the owning workspace removed branding', function (): void {
    $agent = Agent::factory()->create();

    grantRemoveBranding($agent->team->owner);

    expect($agent->fresh()->loadMissing('team.owner')->showsChatviaBranding())->toBeFalse();
});
