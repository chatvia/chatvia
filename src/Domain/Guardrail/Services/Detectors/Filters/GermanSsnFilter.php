<?php

namespace Domain\Guardrail\Services\Detectors\Filters;

/**
 * German social-insurance number (Sozialversicherungsnummer / Rentenversicherungs-
 * nummer): 12 chars = 2-digit area + DDMMYY birth date + 1 surname letter + 2-digit
 * serial + 1 check digit, e.g. "15 070649 C 103". The embedded letter makes false
 * positives rare; {@see accepts()} additionally validates the birth-date segment.
 */
final class GermanSsnFilter extends PiiFilter
{
    protected function pattern(): string
    {
        // AA DDMMYY L NNN — spaces between groups are optional.
        return '/\b\d{2}\s?\d{6}\s?[A-Za-z]\s?\d{3}\b/';
    }

    protected function label(): string
    {
        return 'GERMAN_SSN';
    }

    protected function type(): string
    {
        return 'german_ssn';
    }

    protected function accepts(string $value): bool
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';
        if (strlen($digits) !== 11) {
            return false;
        }

        // Digits 3–8 are the birth date DDMMYY.
        $day = (int) substr($digits, 2, 2);
        $month = (int) substr($digits, 4, 2);

        return $day >= 1 && $day <= 31 && $month >= 1 && $month <= 12;
    }
}
