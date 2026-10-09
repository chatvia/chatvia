<?php

namespace Domain\Billing\Enums;

use App\Identity\Workspace\Models\User;

/**
 * The single source of truth for add-on subscriptions. The case value doubles
 * as the Cashier subscription `type`, the `addon` metadata slug on the Stripe
 * subscription, and the `config('billing.addons.<value>')` key — so adding a
 * new add-on means adding one case here, not editing five files.
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

    /**
     * Whether the customer picks a quantity. Remove Branding is a yes/no
     * add-on, always bought once.
     */
    public function hasQuantity(): bool
    {
        return $this !== self::RemoveBranding;
    }

    /** The add-on's Stripe price, or null when it is not configured. */
    public function stripePriceId(): ?string
    {
        $priceId = config("billing.addons.{$this->value}.stripe_price_id");

        return is_string($priceId) && $priceId !== '' ? $priceId : null;
    }

    /** The environment variable that holds the add-on's Stripe price. */
    public function priceEnvironmentKey(): string
    {
        return match ($this) {
            self::ExtraAgents => 'STRIPE_EXTRA_AGENT_PRICE_ID',
            self::CustomDomains => 'STRIPE_CUSTOM_DOMAIN_PRICE_ID',
            self::RemoveBranding => 'STRIPE_REMOVE_BRANDING_PRICE_ID',
            self::Members => 'STRIPE_MEMBERS_PRICE_ID',
        };
    }

    /** Short English name, used in operator-facing messages. */
    public function label(): string
    {
        return match ($this) {
            self::ExtraAgents => 'Extra Agents',
            self::CustomDomains => 'Custom Domain',
            self::RemoveBranding => 'Remove Branding',
            self::Members => 'Members',
        };
    }
}
