<?php

namespace Domain\Billing\Actions\Webhooks;

use Domain\Billing\Actions\AutoRecharge\AutoRechargeCreditsAction;
use Domain\Billing\Data\Stripe\CompletedCheckoutData;
use Domain\Billing\Enums\MessageCreditAddon;
use Domain\Billing\Models\UserAddon;
use Domain\Billing\Models\UserBilling;
use Domain\Billing\Services\UserCreditSystem;

/**
 * Grants the extra-message pack a customer bought through Stripe Checkout.
 *
 * Keyed on the Checkout Session id, so a redelivered event (after the
 * idempotency claim expired, or on a retry after a later failure) can't grant
 * the same purchase twice or extend its expiry. Auto-recharge packs are not
 * handled here: they are charged off-session and granted on the spot by
 * {@see AutoRechargeCreditsAction}. Returns null when nothing was granted.
 */
class GrantOneOffAddonAction
{
    public function __construct(
        private readonly UserCreditSystem $creditSystem,
    ) {}

    public function execute(CompletedCheckoutData $checkout): ?UserAddon
    {
        $user = UserBilling::forStripeCustomer($checkout->customerId)?->user;
        $pack = $checkout->metadata->messageCreditAddon();

        if ($user === null || $pack !== MessageCreditAddon::ExtraMessageCredits) {
            return null;
        }

        $addon = $user->addons()->firstOrCreate(
            ['stripe_id' => $checkout->sessionId, 'type' => $pack->value],
            [
                'amount' => $checkout->metadata->messages(),
                'started_at' => now(),
                'ends_at' => now()->addMonth(),
            ],
        );

        $user->refresh();
        $this->creditSystem->forWorkspace($user->workspace()->id)->refreshCanSendMessages();

        return $addon;
    }
}
