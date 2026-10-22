<?php

declare(strict_types=1);

namespace Domain\Guardrail\Data;

use Domain\Guardrail\Enums\GuardrailOffenderKind;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The person behind one abusive guardrail trigger, for the Guardrails
 * Activity log: a chatter or an anonymous visitor (by fingerprint), whether
 * they are blocked already, and how many abusive messages they sent in the
 * last 24 hours.
 */
#[TypeScript]
class GuardrailOffenderData extends Data
{
    public function __construct(
        public GuardrailOffenderKind $kind,
        public bool $blocked,
        public int $recent_attempts,
        public ?string $visitor_key = null,
        public ?string $chatter_external_id = null,
    ) {}
}
