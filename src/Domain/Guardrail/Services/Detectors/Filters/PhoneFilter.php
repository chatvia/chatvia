<?php

namespace Domain\Guardrail\Services\Detectors\Filters;

use Illuminate\Support\Str;

/**
 * Masks phone numbers ([PHONE_n]). Runs last, after the stricter number
 * filters, so its looser pattern cannot swallow their digits.
 *
 * A long run of digits is not enough: support chats are full of order,
 * invoice and tracking numbers, dates and times, which must reach the model
 * and the tools unchanged. A candidate counts only when it is written the way
 * phone numbers are: international (`+…`, `00…`), national with a trunk
 * zero (`030 1234567`, `0151 …`), or in the North American 3-3-4 shape.
 */
final class PhoneFilter extends PiiFilter
{
    /** The North American 3-3-4 shape with separators, with an optional leading 1. */
    private const string NORTH_AMERICAN = '/^(?:\+?1[\s.\-]?)?(?:\([2-9]\d{2}\)\s?|[2-9]\d{2}[\s.\-])\d{3}[\s.\-]\d{4}$/';

    /** The same without separators: stricter, as a bare 10-digit run is often an order number. */
    private const string NORTH_AMERICAN_DIGITS = '/^1?[2-9]\d{2}[2-9]\d{6}$/';

    /** Dates (2024-10-15, 15.10.2024), IPv4 addresses and decimals (0.123456789). */
    private const array NOT_PHONES = [
        '/^\d{4}[.\/\-]\d{1,2}[.\/\-]\d{1,2}/',
        '/^\d{1,2}[.\/\-]\d{1,2}[.\/\-]\d{2,4}\b/',
        '/^\d{1,3}(?:\.\d{1,3}){3}$/',
        '/^\d\.\d/',
    ];

    protected function pattern(): string
    {
        // An optional `+` or `(`, then digits with spaces, dots, dashes,
        // slashes or brackets between them. Never inside a word, a path or a
        // `#123` reference, and never the date before a time ("… 12:30").
        return '/(?<![\w#\/.])(?:\(?\+|\()?\d[\d().\/\- ]{7,}\d(?![\w\/]|[.:]\d)/';
    }

    protected function label(): string
    {
        return 'PHONE';
    }

    protected function type(): string
    {
        return 'phone';
    }

    /**
     * 9–15 digits, not a date or an IP address, and written like a phone
     * number (see the class docblock).
     */
    protected function accepts(string $value): bool
    {
        $digits = Str::replaceMatches('/\D/', '', $value) ?? '';
        $length = Str::length($digits);

        if ($length < 9 || $length > 15) {
            return false;
        }

        if (Str::isMatch(self::NOT_PHONES, $value)) {
            return false;
        }

        $start = Str::ltrim($value, '(');

        return Str::startsWith($start, '+')
            || Str::startsWith($start, '0')
            || Str::isMatch(self::NORTH_AMERICAN, $value)
            || Str::isMatch(self::NORTH_AMERICAN_DIGITS, $value);
    }
}
