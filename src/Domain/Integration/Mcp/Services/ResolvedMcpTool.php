<?php

namespace Domain\Integration\Mcp\Services;

use Domain\Integration\Mcp\Enums\McpToolPermission;

/**
 * A tool's resolved runtime state for one server — its stored permission and
 * description — independent of whether it came from the shared catalog or a
 * custom server's per-server rows. {@see McpToolPermissionResolver}.
 */
final class ResolvedMcpTool
{
    public function __construct(
        public readonly McpToolPermission $permission,
        public readonly ?string $description,
    ) {}
}
