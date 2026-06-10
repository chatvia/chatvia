<?php

namespace Domain\Billing\Enums;

use App\Identity\Workspace\Models\User;

/**
 * The single source of truth for addon-subscriptions (extra agents / custom
 * domains). The case value doubles as the Cashier subscription `type`, the
 * `addon` metadata slug on the Stripe subscription, and the
 * `config('billing.addons.<value>.stripe_price_id')` config key — so adding a
 * new addon means adding one case here, not editing five files.
 */
enum Addon: string
{
    case ExtraAgents = 'extra_agents';
    case CustomDomains = 'custom_domains';
    case RemoveBranding = 'remove_branding';
    case Members = 'members';

    /**
     * The `user_quotas` column this addon's subscription quantity mirrors onto,
     * or null for subscription-only addons that keep no quota mirror — their
     * entitlement is read live from the Cashier subscription (e.g.
     * RemoveBranding via {@see User::canHideBranding()}).
     */
    public function quotaColumn(): ?string
    {
        return match ($this) {
            self::ExtraAgents => 'extra_agents_limit',
            self::CustomDomains => 'extra_custom_domains_limit',
            self::RemoveBranding => null,
            self::Members => 'extra_members_limit',
        };
    }
}
