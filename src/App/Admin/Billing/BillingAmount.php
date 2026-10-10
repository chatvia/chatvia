<?php

declare(strict_types=1);

namespace App\Admin\Billing;

/**
 * Prices in the admin panel's billing forms: typed as decimal amounts
 * ("49.00"), stored in the smallest currency unit (4900), always in the
 * billing currency of Settings → Billing.
 */
final class BillingAmount
{
    /** The highest amount a price input accepts. */
    public const int MAX = 999_999;

    private const string DEFAULT_CURRENCY = 'eur';

    /** The billing currency, lower-case as Stripe writes it (`eur`). */
    public static function currency(): string
    {
        $currency = strtolower(trim((string) config('billing.currency')));

        return $currency !== '' ? $currency : self::DEFAULT_CURRENCY;
    }

    /** The billing currency as people read it (`EUR`). */
    public static function currencyCode(): string
    {
        return strtoupper(self::currency());
    }

    /** "49.00" for 4900, or null for no amount. */
    public static function fromCents(?int $cents): ?string
    {
        return $cents === null ? null : number_format($cents / 100, 2, '.', '');
    }

    /** 4900 for "49" or "49.00", or null when nothing was typed. */
    public static function toCents(mixed $amount): ?int
    {
        if ($amount === null || trim((string) $amount) === '') {
            return null;
        }

        return (int) round(((float) $amount) * 100);
    }
}
