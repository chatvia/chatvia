<?php

declare(strict_types=1);

namespace Domain\Billing\Enums;

/**
 * Why a plan or add-on was saved without being sent to Stripe. Without paid
 * plans or without a Stripe secret key there is nothing to send it to; the
 * next save or "Sync with Stripe" after that sends it.
 */
enum StripeSyncBlocker: string
{
    case BillingOff = 'billing_off';
    case NoSecretKey = 'no_secret_key';

    /** The reason, as the admin panel shows it after "Not sent to Stripe:". */
    public function reason(): string
    {
        return match ($this) {
            self::BillingOff => 'paid plans are turned off (Settings → Billing).',
            self::NoSecretKey => 'no Stripe secret key is saved (Settings → Stripe).',
        };
    }
}
