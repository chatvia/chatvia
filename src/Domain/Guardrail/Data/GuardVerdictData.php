<?php

declare(strict_types=1);

namespace Domain\Guardrail\Data;

use Domain\Guardrail\Enums\GuardOutcome;
use Domain\Guardrail\Enums\GuardSeverity;
use Domain\Guardrail\Enums\GuardStage;
use Domain\Guardrail\Enums\GuardType;

/**
 * One guard that fired: which guard, where, what was done, how severe, and the
 * structured detail (PII types / injection risk / topic / toxicity categories).
 * A clean pass produces no verdict.
 */
final readonly class GuardVerdictData
{
    /**
     * @param  array<string, mixed>  $detail
     */
    public function __construct(
        public GuardType $guardType,
        public GuardStage $stage,
        public GuardOutcome $outcome,
        public ?GuardSeverity $severity = null,
        public array $detail = [],
    ) {}
}
