<?php

namespace Domain\Connector\Data\Custom;

use Domain\Mcp\Data\Resources\AgentMcpServerData;
use Domain\Mcp\Models\AgentMcpServer;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A custom (account-level, no catalog entry) connector row on the agent's
 * Connectors page — carries the per-agent attachment flag.
 */
#[TypeScript]
class AgentCustomConnectorRowData extends Data
{
    public function __construct(
        public string $id,
        public string $name,
        public bool $is_attached,
    ) {}

    public static function fromModel(AgentMcpServer $server, bool $isAttached): self
    {
        $base = AgentMcpServerData::fromModel($server)->toArray();

        return new self(
            id: (string) ($base['id'] ?? $server->external_id),
            name: (string) ($base['name'] ?? $server->name),
            is_attached: $isAttached,
        );
    }
}
