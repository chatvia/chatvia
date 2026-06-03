<?php

namespace Domain\Integration\Mcp\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Result of toggling a single tool on a connector — the row id and its new
 * `is_enabled` / `requires_approval` values (so the page can patch the row in
 * place without a refresh).
 */
#[TypeScript]
class ToggledConnectorToolData extends Data
{
    public function __construct(
        public int $id,
        public bool $is_enabled,
        public bool $requires_approval = true,
    ) {}
}
