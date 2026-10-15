<?php

declare(strict_types=1);

namespace Domain\Integration\Mcp\Data\Agent;

use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Spatie\LaravelData\Data;

/**
 * Inputs for attaching an account-level MCP server to an agent: the agent and
 * one of its workspace's servers.
 */
class AttachAgentMcpData extends Data
{
    public function __construct(
        public Agent $agent,
        public AgentMcpServer $server,
    ) {}
}
