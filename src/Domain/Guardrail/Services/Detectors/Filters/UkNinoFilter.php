<?php

declare(strict_types=1);

namespace Domain\Guardrail\Services\Detectors\Filters;

/**
 * Masks UK National Insurance numbers ([UK_NINO_n]): two letters, six
 * digits and a final A–D, e.g. "QQ 12 34 56 C". The letter rules of HMRC
 * (no D, F, I, Q, U or V first; no O second; no BG, GB, KN, NK, NT, TN or
 * ZZ prefix) keep ordinary codes out.
 */
final class UkNinoFilter extends PiiFilter
{
    private const array INVALID_PREFIXES = ['BG', 'GB', 'KN', 'NK', 'NT', 'TN', 'ZZ'];

    protected function pattern(): string
    {
        return '/\b[A-CEGHJ-PR-TW-Z][A-CEGHJ-NPR-TW-Z] ?\d{2} ?\d{2} ?\d{2} ?[A-D]\b/i';
    }

    protected function label(): string
    {
        return 'UK_NINO';
    }

    protected function type(): string
    {
        return 'uk_nino';
    }

    protected function accepts(string $value): bool
    {
        return ! in_array(strtoupper(substr($value, 0, 2)), self::INVALID_PREFIXES, true);
    }
}
