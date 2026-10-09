<?php

declare(strict_types=1);

namespace Domain\Analytics\Data\Agent\Usage;

/**
 * One MCP tool an agent called, with the server it belongs to — labels as
 * they were at call time — its call count and the last day it was called.
 */
final readonly class McpToolUsageData
{
    public function __construct(
        public string $toolName,
        public ?string $toolDescription,
        public ?string $serverName,
        public string $serverSlug,
        public ?string $connectorSlug,
        public int $calls,
        public ?string $lastUsedOn,
    ) {}
}
