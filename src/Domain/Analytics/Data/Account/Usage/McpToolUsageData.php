<?php

namespace Domain\Analytics\Data\Account\Usage;

/**
 * One MCP connector tool and how often the AI called it.
 */
final readonly class McpToolUsageData
{
    public function __construct(
        public string $tool,
        public string $server,
        public int $calls,
    ) {}
}
