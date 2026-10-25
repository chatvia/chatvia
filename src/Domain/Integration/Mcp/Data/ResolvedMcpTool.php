<?php

declare(strict_types=1);

namespace Domain\Integration\Mcp\Data;

use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Services\McpToolPermissionResolver;

/**
 * A tool's resolved runtime state for one server — its stored permission,
 * description and input schema — independent of whether it came from the shared catalog or a
 * custom server's per-server rows. {@see McpToolPermissionResolver}.
 */
final readonly class ResolvedMcpTool
{
    /**
     * @param  array<array-key, mixed>  $inputSchema  the tool's `inputSchema` as the server listed it
     */
    public function __construct(
        public McpToolPermission $permission,
        public ?string $description,
        public array $inputSchema = [],
    ) {}
}
