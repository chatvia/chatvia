<?php

namespace Domain\Integration\Mcp\Data\Resources;

use Carbon\CarbonInterface;
use Domain\Integration\Mcp\Enums\McpAuthType;
use Domain\Integration\Mcp\Enums\McpTransport;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * A workspace MCP server as the Connectors pages show it — never its
 * credentials.
 */
#[TypeScript]
class AgentMcpServerData extends Data
{
    public function __construct(
        public string $external_id,
        public string $name,
        public string $slug,
        public ?string $connector_slug,
        public ?string $description,
        public string $url,
        public McpTransport $transport,
        public McpAuthType $auth_type,
        public int $timeout_ms,
        public bool $is_enabled,
        public int $catalog_tools_count,
        public ?CarbonInterface $catalog_cached_at,
        public ?CarbonInterface $last_invoked_at,
        public ?string $last_error,
        public int $invocation_count,
        public int $agents_count = 0,
    ) {}

    public static function fromModel(AgentMcpServer $server): self
    {
        return new self(
            external_id: $server->external_id,
            name: $server->name,
            slug: $server->slug,
            connector_slug: $server->connector_slug,
            description: $server->description,
            url: $server->url,
            transport: $server->transport,
            auth_type: $server->auth_type,
            timeout_ms: $server->timeout_ms,
            is_enabled: $server->is_enabled,
            catalog_tools_count: $server->catalog_tools_count,
            catalog_cached_at: $server->catalog_cached_at,
            last_invoked_at: $server->last_invoked_at,
            last_error: $server->last_error,
            invocation_count: $server->invocation_count,
            agents_count: $server->agents_count ?? $server->agents()->count(),
        );
    }
}
