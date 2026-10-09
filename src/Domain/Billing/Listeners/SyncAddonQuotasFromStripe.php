<?php

namespace Domain\Billing\Listeners;

use Domain\Billing\Actions\Addons\SyncAddonQuotasAction;
use Domain\Billing\Data\Stripe\StripeEventData;
use Domain\Billing\Data\Stripe\StripeMetadata;
use Domain\Billing\Models\UserBilling;
use Laravel\Cashier\Events\WebhookHandled;

/**
 * Keeps add-on quota columns in step with Stripe whenever an add-on
 * subscription is created, changed or deleted.
 *
 * It listens to Cashier's WebhookHandled (not WebhookReceived) on purpose:
 * Cashier has already written the change to the local `subscriptions` table by
 * then, so {@see SyncAddonQuotasAction} rebuilds the quotas from fresh rows.
 * An add-on subscription is recognised by its metadata
 * ({@see StripeMetadata::subscriptionAddon()}).
 */
class SyncAddonQuotasFromStripe
{
    private const array SUBSCRIPTION_EVENTS = [
        'customer.subscription.created',
        'customer.subscription.updated',
        'customer.subscription.deleted',
    ];

    public function __construct(
        private readonly SyncAddonQuotasAction $syncAddonQuotas,
    ) {}

    public function handle(WebhookHandled $event): void
    {
        // Billing turned off: Stripe events never change quotas.
        if (! config('billing.enabled', true)) {
            return;
        }

        $stripeEvent = StripeEventData::fromPayload($event->payload);

        if (! in_array($stripeEvent->type, self::SUBSCRIPTION_EVENTS, true)) {
            return;
        }

        if ($stripeEvent->metadata()->subscriptionAddon() === null) {
            return;
        }

        $customerId = $stripeEvent->object['customer'] ?? null;
        $billing = UserBilling::forStripeCustomer(is_string($customerId) ? $customerId : null);

        if ($billing !== null) {
            $this->syncAddonQuotas->execute($billing);
        }
    }
}
