<?php

declare(strict_types=1);

namespace Domain\Integration\Mcp\Data;

use Domain\Integration\Mcp\Enums\McpToolPermission;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One connector tool's permission: the tool row id and its permission value.
 * The input of setting a tool's permission and its result (so the page can
 * patch the row in place without a refresh).
 */
#[TypeScript]
class McpToolPermissionData extends Data
{
    public function __construct(
        public int $id,
        public McpToolPermission $permission,
    ) {}
}
