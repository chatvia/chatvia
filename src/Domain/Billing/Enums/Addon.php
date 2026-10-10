<?php

declare(strict_types=1);

namespace Domain\Billing\Enums;

use App\Identity\Workspace\Models\User;
use Domain\Billing\Services\Catalog\BillingCatalog;

/**
 * The single source of truth for add-on subscriptions. The case value doubles
 * as the Cashier subscription `type`, the `addon` metadata slug on the Stripe
 * subscription, and the `billing_addons.key` of the row that holds its price —
 * so adding a new add-on means adding one case here (and its row), not editing
 * five files.
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

    /** The add-on's Stripe price, or null when it has none or is not sold (inactive). */
    public function stripePriceId(): ?string
    {
        return app(BillingCatalog::class)->addon($this)?->purchasablePriceId();
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
