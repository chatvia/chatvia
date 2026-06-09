<?php

namespace Domain\Guardrail\Services\Detectors\Filters;

/**
 * Danish CPR number: 10 digits as DDMMYY-SSSS (the dash is optional), where the
 * last 4 are a sequence number. {@see accepts()} validates the embedded birth
 * date only — the historical mod-11 check digit was abandoned in 2007, so a
 * checksum gate would reject real, valid CPRs and is deliberately omitted.
 *
 * The 10-digit shape overlaps with ordinary phone numbers, so this filter is
 * opt-in (off by default) and runs before PhoneFilter in the pipeline.
 */
final class DanishCprFilter extends PiiFilter
{
    protected function pattern(): string
    {
        return '/\b\d{6}-?\d{4}\b/';
    }

    protected function label(): string
    {
        return 'DANISH_CPR';
    }

    protected function type(): string
    {
        return 'danish_cpr';
    }

    protected function accepts(string $value): bool
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';
        if (strlen($digits) !== 10) {
            return false;
        }

        $day = (int) substr($digits, 0, 2);
        $month = (int) substr($digits, 2, 2);

        return $day >= 1 && $day <= 31 && $month >= 1 && $month <= 12;
    }
}
