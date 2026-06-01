<?php

namespace Domain\Agents\Data\Mcp;

use Domain\Agents\Models\Agent;
use Spatie\LaravelData\Data;

/**
 * Inputs for attaching an account-level MCP server to an agent. `mcp_id` is
 * the server's `external_id` (not the internal primary key) — it's what the
 * API surface exposes to clients.
 */
class AttachAgentMcpData extends Data
{
    public function __construct(
        public Agent $agent,
        public string $mcp_id,
    ) {}
}
