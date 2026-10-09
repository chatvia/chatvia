<?php

declare(strict_types=1);

namespace Domain\Guardrail\Services\Detectors\Filters;

/**
 * Masks US Social Security Numbers in the NNN-NN-NNNN form ([US_SSN_n]).
 */
final class UsSsnFilter extends PiiFilter
{
    protected function pattern(): string
    {
        return '/\b\d{3}-\d{2}-\d{4}\b/';
    }

    protected function label(): string
    {
        return 'US_SSN';
    }

    protected function type(): string
    {
        return 'us_ssn';
    }
}
