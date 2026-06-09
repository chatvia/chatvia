<?php

namespace Domain\Guardrail\Services\Detectors\Filters;

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
