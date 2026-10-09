<?php

namespace Domain\Billing\Enums;

/**
 * One-off message-credit packs. The value is three things at once: the `id`
 * metadata key on the Stripe checkout or invoice, the `user_addons.type`
 * column, and the `billing.addons.<value>` config key.
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

    /** Messages one unit of the pack adds. */
    public function unitMessages(): int
    {
        return (int) config("billing.addons.{$this->value}.unit_messages", 1000);
    }

    /** The Stripe price of one unit, or null when it is not configured. */
    public function stripePriceId(): ?string
    {
        $priceId = config("billing.addons.{$this->value}.stripe_price_id");

        return is_string($priceId) && $priceId !== '' ? $priceId : null;
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
