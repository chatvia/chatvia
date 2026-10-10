<?php

namespace Domain\Billing\Enums;

use Domain\Billing\Services\Catalog\BillingCatalog;

/**
 * One-off message-credit packs. The value is three things at once: the `id`
 * metadata key on the Stripe checkout or invoice, the `user_addons.type`
 * column, and the `billing_addons.key` of the row that holds its price.
 *
 *  - ExtraMessageCredits: bought by the customer through Stripe Checkout.
 *  - AutoRechargeCredits: charged off-session when the balance runs low.
 *
 * Both packs add to the workspace's message pool for one month.
 */
enum MessageCreditAddon: string
{
    case ExtraMessageCredits = 'extra_message_credits';
    case AutoRechargeCredits = 'auto_recharge_credits';

    /** Messages one unit of the pack adds when none is set. */
    private const int DEFAULT_UNIT_MESSAGES = 1000;

    /** Messages one unit of the pack adds. */
    public function unitMessages(): int
    {
        return app(BillingCatalog::class)->addon($this)?->unit_messages ?? self::DEFAULT_UNIT_MESSAGES;
    }

    /** The Stripe price of one unit, or null when it has none or is not sold (inactive). */
    public function stripePriceId(): ?string
    {
        return app(BillingCatalog::class)->addon($this)?->purchasablePriceId();
    }

    /**
     * Every pack's `user_addons.type` value, for queries that sum the pool.
     *
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(fn (self $addon): string => $addon->value, self::cases());
    }
}
