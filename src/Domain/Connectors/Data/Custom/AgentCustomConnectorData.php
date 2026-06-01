<?php

namespace Domain\Connectors\Data\Custom;

use Domain\Agents\Models\Agent;
use Spatie\LaravelData\Data;

/**
 * Agent-scoped custom MCP connector payload — same shape as
 * `AddCustomConnectorData` but tied to an `Agent` route binding instead of
 * the workspace user, since this connector belongs to one agent.
 */
class AgentCustomConnectorData extends Data
{
    public function __construct(
        public Agent $agent,
        public string $name,
        public string $url,
        public string $auth_strategy,
        public ?string $token = null,
    ) {}
}
