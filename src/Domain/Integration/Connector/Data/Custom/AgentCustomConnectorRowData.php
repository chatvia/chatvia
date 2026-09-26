<?php

namespace Domain\Integration\Connector\Data\Custom;

use Domain\Integration\Mcp\Models\AgentMcpServer;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A custom (account-level, no catalog entry) connector row on the agent's
 * Connectors page — the server identity the page needs to attach/detach it
 * (slug), what it shows (url, tool count), plus the per-agent flags.
 */
#[TypeScript]
class AgentCustomConnectorRowData extends Data
{
    public function __construct(
        public string $external_id,
        public string $name,
        public string $slug,
        public string $url,
        public bool $is_enabled,
        public int $catalog_tools_count,
        public bool $is_attached,
        /** Attached AND marked "always on" for this agent: its tools ship on every turn. */
        public bool $always_available = false,
    ) {}

    public static function fromModel(AgentMcpServer $server, bool $isAttached, bool $alwaysAvailable = false): self
    {
        return new self(
            external_id: $server->external_id,
            name: $server->name,
            slug: $server->slug,
            url: $server->url,
            is_enabled: $server->is_enabled,
            catalog_tools_count: $server->catalog_tools_count,
            is_attached: $isAttached,
            always_available: $isAttached && $alwaysAvailable,
        );
    }
}
