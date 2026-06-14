<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Actions\Subscriptions\CancelAddonAction;
use Domain\Billing\Enums\Addon;
use Domain\Billing\Models\UserQuota;
use Illuminate\Support\Str;

/**
 * CancelAddonAction replaces the byte-identical per-addon Cancel*Action family.
 * The behaviour the consolidation must preserve — and the only thing that ever
 * differed between the old actions — is the quota handling, driven by
 * {@see Addon::quotaColumn()}:
 *   - column-backed addons (extra agents / members / custom domains) zero their
 *     mirror column;
 *   - the boolean RemoveBranding addon has no column, so it must NOT touch (or
 *     create) a user_quotas row.
 *
 * The Cashier `cancelNow()` branch is identical across every addon and hits the
 * live Stripe API for an *active* subscription (which is why the originals had no
 * unit tests). To exercise the action without a Stripe round-trip these tests use
 * an already-canceled subscription (`ends_at` set → {@see Subscription::canceled()}
 * true), so the cancel branch is correctly skipped and the quota logic — the part
 * that actually varies — is what gets pinned.
 */
function grantCanceledAddonSubscription(User $user, Addon $addon): void
{
    $billing = $user->billing()->updateOrCreate([], ['stripe_id' => 'cus_'.Str::random(14)]);

    $billing->subscriptions()->create([
        'type' => $addon->value,
        'stripe_id' => 'sub_'.Str::random(20),
        'stripe_status' => 'canceled',
        'stripe_price' => 'price_test_'.$addon->value,
        'quantity' => 1,
        'ends_at' => now()->subDay(),
    ]);
}

it('zeroes the quota column for a column-backed addon', function (): void {
    $user = User::factory()->workspace()->create();
    grantCanceledAddonSubscription($user, Addon::ExtraAgents);
    $user->quota()->updateOrCreate([], ['extra_agents_limit' => 5]);

    app(CancelAddonAction::class)->execute($user, Addon::ExtraAgents);

    expect((int) $user->quota()->first()->extra_agents_limit)->toBe(0);
});

it('only zeroes the targeted addon column and leaves sibling addon quotas intact', function (): void {
    $user = User::factory()->workspace()->create();
    grantCanceledAddonSubscription($user, Addon::Members);
    $user->quota()->updateOrCreate([], [
        'extra_members_limit' => 4,
        'extra_agents_limit' => 7,
    ]);

    app(CancelAddonAction::class)->execute($user, Addon::Members);

    $quota = $user->quota()->first();
    expect((int) $quota->extra_members_limit)->toBe(0)
        ->and((int) $quota->extra_agents_limit)->toBe(7);
});

it('does not create or touch a quota row for the column-less remove-branding addon', function (): void {
    $user = User::factory()->workspace()->create();
    grantCanceledAddonSubscription($user, Addon::RemoveBranding);

    // No quota row exists going in; the action must not create one for a
    // column-less boolean addon (the key divergence from the other addons).
    expect(UserQuota::query()->where('user_id', $user->id)->exists())->toBeFalse();

    app(CancelAddonAction::class)->execute($user, Addon::RemoveBranding);

    expect(UserQuota::query()->where('user_id', $user->id)->exists())->toBeFalse();
});

it('still resets the column-backed quota when there is no subscription to cancel', function (): void {
    $user = User::factory()->workspace()->create();
    // Provision a billing row but no addon subscription.
    $user->billing()->updateOrCreate([], ['stripe_id' => 'cus_'.Str::random(14)]);
    $user->quota()->updateOrCreate([], ['extra_agents_limit' => 3]);

    app(CancelAddonAction::class)->execute($user, Addon::ExtraAgents);

    expect((int) $user->quota()->first()->extra_agents_limit)->toBe(0);
});
