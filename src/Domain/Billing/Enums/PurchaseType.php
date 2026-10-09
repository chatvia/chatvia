<?php

declare(strict_types=1);

namespace Domain\Billing\Enums;

/**
 * The `type` metadata value we put on Stripe checkouts and subscriptions, so
 * the webhook can tell what was bought:
 *  - Addon: a one-off purchase of message credits (see {@see MessageCreditAddon}).
 *  - AddonSubscription: a recurring add-on (see {@see Addon}).
 *  - Subscription: the plan itself.
 *
 * Cashier's Checkout replaces the `type` key of a subscription's metadata with
 * the Cashier subscription type, so readers of add-on subscriptions also check
 * the `addon` key.
 */
enum PurchaseType: string
{
    case Addon = 'addon';
    case AddonSubscription = 'addon_subscription';
    case Subscription = 'subscription';
}
