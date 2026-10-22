<?php

declare(strict_types=1);

namespace Domain\Conversation\Data\Audit;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * The active filters of a tool-call audit page. A null field means the page
 * is not filtering on it; the per-agent viewer never sets `agent_id` (its
 * agent comes from the route).
 */
#[TypeScript]
class ToolCallAuditFiltersData extends Data
{
    public function __construct(
        public ?string $outcome,
        public ?string $connector,
        public ?int $agent_id = null,
    ) {}
}
