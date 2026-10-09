<?php

namespace Domain\Integration\Mcp\Data;

use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Services\McpToolPermissionResolver;

/**
 * A tool's resolved runtime state for one server — its stored permission and
 * description — independent of whether it came from the shared catalog or a
 * custom server's per-server rows. {@see McpToolPermissionResolver}.
 */
final readonly class ResolvedMcpTool
{
    public function __construct(
        public McpToolPermission $permission,
        public ?string $description,
    ) {}
}
