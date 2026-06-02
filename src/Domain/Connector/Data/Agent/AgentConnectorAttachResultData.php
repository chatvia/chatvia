<?php

namespace Domain\Connector\Data\Agent;

use Domain\Mcp\Data\Resources\AgentMcpServerData;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Result of attaching an account-level connector to an agent — the server,
 * marked attached so the page can patch its row in place.
 */
#[TypeScript]
class AgentConnectorAttachResultData extends Data
{
    public function __construct(
        public AgentMcpServerData $data,
        public bool $is_attached = true,
    ) {}
}
