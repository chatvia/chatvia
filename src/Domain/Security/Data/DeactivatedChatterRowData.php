<?php

namespace Domain\Security\Data;

use App\Identity\Chatter\Models\Chatter;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One switched-off chatter on the Blocked lists (Guardrails "Blocked" tab and
 * Security → Blocked).
 */
#[TypeScript]
class DeactivatedChatterRowData extends Data
{
    public function __construct(
        public string $external_id,
        public string $name,
        public ?string $reason,
        public ?string $deactivated_at,
    ) {}

    public static function fromModel(Chatter $chatter): self
    {
        return new self(
            external_id: $chatter->external_id,
            name: $chatter->name,
            reason: $chatter->deactivation_reason,
            deactivated_at: $chatter->deactivated_at?->toIso8601String(),
        );
    }
}
