<?php

namespace Domain\Connector\Data\Agent;

use Domain\Mcp\Data\Resources\AgentMcpServerData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Outcome of re-fetching an agent's connector tool catalog — the refreshed
 * server snapshot plus the new tool count.
 */
#[TypeScript]
class AgentConnectorRefreshResultData extends Data
{
    public function __construct(
        public AgentMcpServerData $data,
        public int $tools_count,
    ) {}
}
