<?php

declare(strict_types=1);

namespace Domain\Guardrail\Services\Detectors\Filters;

use Illuminate\Support\Str;

/**
 * Masks bank account numbers in IBAN form ([IBAN_n]), with or without the
 * usual groups of four. Only a candidate that passes the ISO 13616 mod-97
 * check counts, so product codes that merely look alike stay readable. Runs
 * before the card and phone filters, which would otherwise take its digits.
 */
final class IbanFilter extends PiiFilter
{
    protected function pattern(): string
    {
        // Country code, check digits, then groups of four upper-case letters
        // or digits and a shorter last group. Upper case only, so the pattern
        // cannot run on into the words after the number.
        return '/\b[A-Z]{2}\d{2}(?: ?[A-Z0-9]{4}){2,7}(?: ?[A-Z0-9]{1,3})?\b/';
    }

    protected function label(): string
    {
        return 'IBAN';
    }

    protected function type(): string
    {
        return 'iban';
    }

    protected function accepts(string $value): bool
    {
        $iban = Str::upper(Str::replace(' ', '', $value));
        $length = Str::length($iban);

        if ($length < 15 || $length > 34) {
            return false;
        }

        // Move the first four characters to the end, letters become 10–35,
        // and the number must leave a remainder of 1 when divided by 97.
        $rearranged = substr($iban, 4).substr($iban, 0, 4);
        $remainder = 0;

        foreach (str_split($rearranged) as $char) {
            $digits = ctype_alpha($char) ? (string) (ord($char[0]) - 55) : $char;

            foreach (str_split($digits) as $digit) {
                $remainder = ($remainder * 10 + (int) $digit) % 97;
            }
        }

        return $remainder === 1;
    }
}
