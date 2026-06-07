<?php

namespace Domain\Billing\Enums;

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

    /** The `user_quotas` column this addon's subscription quantity mirrors onto. */
    public function quotaColumn(): string
    {
        return match ($this) {
            self::ExtraAgents => 'extra_agents_limit',
            self::CustomDomains => 'extra_custom_domains_limit',
        };
    }
}
