<?php

namespace Domain\Guardrail\Data;

use Domain\Guardrail\Enums\GuardOutcome;
use Domain\Guardrail\Enums\GuardType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The active filters of the Guardrails trigger log. A null field means the
 * page is not filtering on it; `visitor` is a visitor's fingerprint.
 */
#[TypeScript]
class GuardrailLogFiltersData extends Data
{
    public function __construct(
        public ?GuardType $guard_type,
        public ?GuardOutcome $outcome,
        public ?string $visitor,
    ) {}
}
