<?php

declare(strict_types=1);

namespace Domain\Integration\Mcp\Data;

use Domain\Integration\Mcp\Models\AgentMcpServer;
use Spatie\LaravelData\Data;

/**
 * A server that was just connected or added, with the number of tools its
 * first catalog sync found.
 */
class ProvisionedMcpServerData extends Data
{
    public function __construct(
        public AgentMcpServer $server,
        public int $tools_count,
    ) {}
}
