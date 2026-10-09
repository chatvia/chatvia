<?php

namespace Domain\Guardrail\Services\Detectors\Filters;

/**
 * Masks payment card numbers ([CARD_n]); only digit runs that pass the Luhn
 * check count, so ordinary long numbers stay readable.
 */
final class CardFilter extends PiiFilter
{
    protected function pattern(): string
    {
        // Candidate card: 13–19 digits with optional space/dash grouping.
        return '/\b(?:\d[ \-]?){13,19}\b/';
    }

    protected function label(): string
    {
        return 'CARD';
    }

    protected function type(): string
    {
        return 'credit_card';
    }

    /**
     * A digit run is a card only if it has 13–19 digits AND passes the Luhn
     * checksum — this rejects order numbers, IDs, and other long integers.
     */
    protected function accepts(string $value): bool
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';
        $len = strlen($digits);

        return $len >= 13 && $len <= 19 && $this->luhn($digits);
    }

    private function luhn(string $digits): bool
    {
        $sum = 0;
        $alt = false;
        for ($i = strlen($digits) - 1; $i >= 0; $i--) {
            $n = (int) $digits[$i];
            if ($alt) {
                $n *= 2;
                if ($n > 9) {
                    $n -= 9;
                }
            }
            $sum += $n;
            $alt = ! $alt;
        }

        return $sum % 10 === 0;
    }
}
