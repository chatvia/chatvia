<?php

namespace Domain\Guardrail\Data;

use Domain\Guardrail\Enums\GuardOutcome;
use Domain\Guardrail\Enums\GuardSeverity;
use Domain\Guardrail\Enums\GuardStage;
use Domain\Guardrail\Enums\GuardType;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One guard that fired during "Test a message", as the Guardrails page
 * renders it.
 */
#[TypeScript]
class GuardrailTestVerdictData extends Data
{
    /**
     * @param  array<string, mixed>  $detail
     */
    public function __construct(
        public GuardType $guard_type,
        public GuardStage $stage,
        public GuardOutcome $outcome,
        public ?GuardSeverity $severity,
        public array $detail,
    ) {}

    public static function fromVerdict(GuardVerdictData $verdict): self
    {
        return new self(
            guard_type: $verdict->guardType,
            stage: $verdict->stage,
            outcome: $verdict->outcome,
            severity: $verdict->severity,
            detail: $verdict->detail,
        );
    }
}
