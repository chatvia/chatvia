<?php

declare(strict_types=1);

namespace Domain\Integration\Mcp\Data;

use Domain\Integration\Mcp\Enums\McpToolPermission;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Result of setting a tool's permission — the row id and its new permission
 * value (so the page can patch the row in place without a refresh).
 */
#[TypeScript]
class McpToolPermissionData extends Data
{
    public function __construct(
        public int $id,
        public McpToolPermission $permission,
    ) {}
}
