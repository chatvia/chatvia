<?php

namespace App\Billing\Data\Resources\Subscription;

use App\Workspace\Models\User;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Snapshot of the user's addon state shown on the billing dashboard:
 *
 * - auto-recharge config (enabled/threshold/amount + when it last fired)
 * - active extra-credit grants (one-time top-ups + auto-recharge purchases)
 * - extra-agents subscription quantity (if any)
 * - custom-domain subscription quantity (if any)
 *
 * Built once per page-load from the User and its billing relationships;
 * no Stripe calls happen here.
 */
#[TypeScript]
class BillingAddonsData extends Data
{
    public function __construct(
        public BillingAutoRechargeData $auto_recharge,
        public BillingAddonMessageCreditsData $extra_message_credits,
        public BillingAddonSubscriptionData $extra_agents,
        public BillingAddonSubscriptionData $custom_domains,
    ) {}

    public static function fromUser(User $user): self
    {
        $billing = $user->accountBilling();

        $extraMessages = (int) $user->addons()
            ->where('type', 'extra_message_credits')
            ->where('ends_at', '>=', now())
            ->sum('amount');

        $autoRechargeCredits = (int) $user->addons()
            ->where('type', 'auto_recharge_credits')
            ->where('ends_at', '>=', now())
            ->sum('amount');

        $extraAgentsSub = $billing?->subscription('extra_agents');
        $customDomainsSub = $billing?->subscription('custom_domains');

        return new self(
            auto_recharge: new BillingAutoRechargeData(
                enabled: (bool) $billing?->auto_recharge_enabled,
                threshold: (int) ($billing?->auto_recharge_threshold ?? 1000),
                amount: (int) ($billing?->auto_recharge_amount ?? 1000),
                last_triggered_at: $billing?->auto_recharge_last_triggered_at?->toFormattedDateString(),
            ),
            extra_message_credits: new BillingAddonMessageCreditsData(
                manual_messages: $extraMessages,
                auto_recharge_messages: $autoRechargeCredits,
            ),
            extra_agents: new BillingAddonSubscriptionData(
                active: (bool) $extraAgentsSub?->active(),
                quantity: $extraAgentsSub && $extraAgentsSub->active() ? (int) $extraAgentsSub->quantity : 0,
                cancels_at: $extraAgentsSub?->ends_at?->toFormattedDateString(),
            ),
            custom_domains: new BillingAddonSubscriptionData(
                active: (bool) $customDomainsSub?->active(),
                quantity: $customDomainsSub && $customDomainsSub->active() ? (int) $customDomainsSub->quantity : 0,
                cancels_at: $customDomainsSub?->ends_at?->toFormattedDateString(),
            ),
        );
    }
}
