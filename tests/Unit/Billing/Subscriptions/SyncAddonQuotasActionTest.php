<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Subscriptions\SyncAddonQuotasAction;
use Domain\Billing\Enums\Addon;
use Domain\Billing\Models\UserBilling;
use Illuminate\Support\Str;

/**
 * Add-on quota columns are rebuilt from the local Cashier subscription rows,
 * which Cashier keeps in sync with Stripe. These tests pin the mapping:
 * valid subscription → its quantity, anything else → 0.
 */
function billingWithAddonSubscription(User $user, Addon $addon, string $status, int $quantity, ?DateTimeInterface $endsAt = null): UserBilling
{
    $billing = $user->billing()->updateOrCreate([], ['stripe_id' => 'cus_'.Str::random(14)]);

    $billing->subscriptions()->create([
        'type' => $addon->value,
        'stripe_id' => 'sub_'.Str::random(20),
        'stripe_status' => $status,
        'stripe_price' => 'price_test_'.$addon->value,
        'quantity' => $quantity,
        'ends_at' => $endsAt,
    ]);

    return $billing;
}

it('grants the quantity of an active add-on subscription', function (): void {
    $user = User::factory()->workspace()->create();
    $billing = billingWithAddonSubscription($user, Addon::ExtraAgents, 'active', 5);

    app(SyncAddonQuotasAction::class)->execute($billing);

    expect((int) $user->quota()->first()->extra_agents_limit)->toBe(5);
});

it('syncs every quota-backed add-on and zeroes the ones without a subscription', function (): void {
    $user = User::factory()->workspace()->create();
    $billing = billingWithAddonSubscription($user, Addon::Members, 'active', 3);
    $user->quota()->updateOrCreate([], ['extra_custom_domains_limit' => 2]);

    app(SyncAddonQuotasAction::class)->execute($billing);

    $quota = $user->quota()->first();
    expect((int) $quota->extra_members_limit)->toBe(3)
        ->and((int) $quota->extra_agents_limit)->toBe(0)
        ->and((int) $quota->extra_custom_domains_limit)->toBe(0);
});

it('keeps the quantity during the grace period of a cancelled subscription', function (): void {
    $user = User::factory()->workspace()->create();
    $billing = billingWithAddonSubscription($user, Addon::CustomDomains, 'active', 2, now()->addWeek());

    app(SyncAddonQuotasAction::class)->execute($billing);

    expect((int) $user->quota()->first()->extra_custom_domains_limit)->toBe(2);
});

it('grants nothing for an ended or incomplete subscription', function (string $status, ?DateTimeInterface $endsAt): void {
    $user = User::factory()->workspace()->create();
    $billing = billingWithAddonSubscription($user, Addon::ExtraAgents, $status, 4, $endsAt);
    $user->quota()->updateOrCreate([], ['extra_agents_limit' => 4]);

    app(SyncAddonQuotasAction::class)->execute($billing);

    expect((int) $user->quota()->first()->extra_agents_limit)->toBe(0);
})->with([
    'canceled and ended' => ['canceled', now()->subDay()],
    'incomplete payment' => ['incomplete', null],
    'incomplete_expired' => ['incomplete_expired', null],
]);
