<?php

namespace Domain\Guardrail\Services\Detectors\Filters;

final class PhoneFilter extends PiiFilter
{
    protected function pattern(): string
    {
        // Candidate phone: a +prefix or digit, then 7+ more digit/separator chars.
        return '/(?<!\w)\+?\d[\d().\-\s]{7,}\d(?!\w)/';
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
     * Require 9–15 digits so the loose pattern doesn't mask short numbers; the
     * card filter runs earlier and has already consumed real card numbers.
     */
    protected function accepts(string $value): bool
    {
        $digits = preg_replace('/\D/', '', $value) ?? '';
        $len = strlen($digits);

        return $len >= 9 && $len <= 15;
    }
}
