<?php

namespace Domain\Mcp\Data;

use Domain\Mcp\Models\AgentMcpServer;
use Spatie\LaravelData\Data;

class ProvisionedMcpServerData extends Data
{
    public function __construct(
        public AgentMcpServer $server,
        public int $tools_count,
    ) {}
}
