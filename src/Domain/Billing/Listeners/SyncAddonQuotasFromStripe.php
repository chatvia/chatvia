<?php

namespace Domain\Billing\Listeners;

use Domain\Billing\Actions\Subscriptions\SyncAddonQuotasAction;
use Domain\Billing\Enums\Addon;
use Domain\Billing\Models\UserBilling;
use Laravel\Cashier\Events\WebhookHandled;

/**
 * Keeps add-on quota columns in step with Stripe whenever an add-on
 * subscription is created, changed or deleted.
 *
 * It listens to Cashier's WebhookHandled (not WebhookReceived) on purpose:
 * Cashier has already written the change to the local `subscriptions` table by
 * then, so {@see SyncAddonQuotasAction} rebuilds the quotas from fresh rows.
 *
 * An add-on subscription is recognised by its metadata. Cashier's Checkout
 * overwrites the `type` metadata key with the Cashier subscription type
 * (`extra_agents`, `members`, …), so both `type` and our own `addon` key are
 * checked against the {@see Addon} cases.
 */
class SyncAddonQuotasFromStripe
{
    private const SUBSCRIPTION_EVENTS = [
        'customer.subscription.created',
        'customer.subscription.updated',
        'customer.subscription.deleted',
    ];

    public function __construct(
        private readonly SyncAddonQuotasAction $syncAddonQuotas,
    ) {}

    public function handle(WebhookHandled $event): void
    {
        if (! in_array($event->payload['type'] ?? null, self::SUBSCRIPTION_EVENTS, true)) {
            return;
        }

        $subscription = $event->payload['data']['object'] ?? [];

        if (! $this->isAddonSubscription($subscription)) {
            return;
        }

        $billing = UserBilling::query()->where('stripe_id', $subscription['customer'] ?? null)->first();

        if ($billing !== null) {
            $this->syncAddonQuotas->execute($billing);
        }
    }

    /**
     * @param  array<string, mixed>  $subscription
     */
    private function isAddonSubscription(array $subscription): bool
    {
        $metadata = $subscription['metadata'] ?? [];

        return Addon::tryFrom((string) ($metadata['addon'] ?? '')) !== null
            || Addon::tryFrom((string) ($metadata['type'] ?? '')) !== null;
    }
}
