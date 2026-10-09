<?php

declare(strict_types=1);

namespace Domain\Guardrail\Services\Detectors\Filters;

/**
 * Masks email addresses as `[EMAIL_n]` in the PII redaction pass. The same
 * address gets the same number each time it appears, so the masked text stays
 * readable.
 */
final class EmailFilter extends PiiFilter
{
    protected function pattern(): string
    {
        return '/[A-Za-z0-9._%+\-]+@[A-Za-z0-9.\-]+\.[A-Za-z]{2,}/';
    }

    protected function label(): string
    {
        return 'EMAIL';
    }

    protected function type(): string
    {
        return 'email';
    }
}
