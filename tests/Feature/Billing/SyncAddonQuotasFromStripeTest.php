<?php

use App\Identity\Workspace\Models\User;
use Domain\Billing\Enums\Addon;
use Domain\Billing\Listeners\SyncAddonQuotasFromStripe;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;
use Laravel\Cashier\Events\WebhookHandled;

/**
 * Regression: add-ons bought through Cashier Checkout never reached
 * user_quotas. Cashier overwrites the subscription's `type` metadata with its
 * own subscription type (`extra_agents`, `members`, …) and puts no metadata on
 * the Checkout session, so the old listener — which waited for
 * `type === 'addon_subscription'` — never ran. These payloads mirror what
 * Stripe really sends for a Cashier Checkout subscription.
 */
function cashierAddonSubscriptionEvent(string $type, string $customerId, Addon $addon, int $quantity, string $status = 'active'): array
{
    return [
        'id' => 'evt_'.Str::random(12),
        'type' => $type,
        'data' => [
            'object' => [
                'id' => 'sub_'.Str::random(14),
                'object' => 'subscription',
                'customer' => $customerId,
                'status' => $status,
                'quantity' => $quantity,
                'metadata' => [
                    // Cashier's array_merge() overwrites our `type` with the Cashier type.
                    'type' => $addon->value,
                    'name' => $addon->value,
                    'addon' => $addon->value,
                    'quantity' => (string) $quantity,
                ],
            ],
        ],
    ];
}

function workspaceWithAddonSubscription(Addon $addon, string $status, int $quantity, ?DateTimeInterface $endsAt = null): User
{
    $user = User::factory()->workspace()->create();
    $billing = $user->billing()->create(['stripe_id' => 'cus_'.Str::random(14)]);
    $billing->subscriptions()->create([
        'type' => $addon->value,
        'stripe_id' => 'sub_'.Str::random(20),
        'stripe_status' => $status,
        'stripe_price' => 'price_test_'.$addon->value,
        'quantity' => $quantity,
        'ends_at' => $endsAt,
    ]);

    return $user;
}

it('grants a newly bought add-on after Cashier stores the subscription', function (): void {
    $user = workspaceWithAddonSubscription(Addon::ExtraAgents, 'active', 5);

    app(SyncAddonQuotasFromStripe::class)->handle(new WebhookHandled(
        cashierAddonSubscriptionEvent('customer.subscription.created', $user->billing->stripe_id, Addon::ExtraAgents, 5)
    ));

    expect((int) $user->quota()->first()->extra_agents_limit)->toBe(5);
});

it('resets the quota when the add-on subscription is deleted', function (): void {
    $user = workspaceWithAddonSubscription(Addon::Members, 'canceled', 3, now()->subMinute());
    $user->quota()->updateOrCreate([], ['extra_members_limit' => 3]);

    app(SyncAddonQuotasFromStripe::class)->handle(new WebhookHandled(
        cashierAddonSubscriptionEvent('customer.subscription.deleted', $user->billing->stripe_id, Addon::Members, 3, 'canceled')
    ));

    expect((int) $user->quota()->first()->extra_members_limit)->toBe(0);
});

it('ignores plan subscriptions and unrelated events', function (string $eventType, array $metadata): void {
    $user = workspaceWithAddonSubscription(Addon::ExtraAgents, 'active', 5);
    $user->quota()->updateOrCreate([], ['extra_agents_limit' => 1]);

    $payload = cashierAddonSubscriptionEvent($eventType, $user->billing->stripe_id, Addon::ExtraAgents, 5);
    $payload['data']['object']['metadata'] = $metadata;

    app(SyncAddonQuotasFromStripe::class)->handle(new WebhookHandled($payload));

    expect((int) $user->quota()->first()->extra_agents_limit)->toBe(1);
})->with([
    'plan subscription' => ['customer.subscription.updated', ['type' => 'default', 'name' => 'default']],
    'invoice event' => ['invoice.payment_succeeded', ['addon' => 'extra_agents']],
]);

it('repairs existing workspaces with the sync command', function (): void {
    $user = workspaceWithAddonSubscription(Addon::CustomDomains, 'active', 2);
    $user->quota()->updateOrCreate([], ['extra_custom_domains_limit' => 0]);

    Artisan::call('billing:sync-addon-quotas');

    expect((int) $user->quota()->first()->extra_custom_domains_limit)->toBe(2);
});
