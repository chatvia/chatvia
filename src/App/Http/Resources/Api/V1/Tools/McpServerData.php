<?php

namespace App\Http\Resources\Api\V1\Tools;

use Carbon\CarbonInterface;
use Domain\Agents\Models\AgentMcpServer;
use Spatie\LaravelData\Data;
use Support\Enums\Mcp\McpAuthType;
use Support\Enums\Mcp\McpTransport;

/**
 * A custom MCP server connected to your account. Pass `id` as `mcp_id` to
 * attach the server to an agent. Auth secrets are never returned;
 * `has_auth_value` only signals whether one is set.
 */
class McpServerData extends Data
{
    public function __construct(
        /**
         * The MCP server's ID.
         */
        public string $id,
        /**
         * Display name of the server.
         */
        public string $name,
        /**
         * URL-friendly slug for the server.
         */
        public ?string $slug,
        /**
         * Endpoint URL the server is reached at.
         */
        public ?string $url,
        /**
         * Transport protocol used to reach the server.
         */
        public McpTransport $transport,
        /**
         * How outbound calls to the server are authenticated.
         */
        public McpAuthType $auth_type,
        /**
         * Whether an auth secret is stored (the secret itself is never returned).
         */
        public bool $has_auth_value,
        /**
         * Whether custom headers are configured.
         */
        public bool $has_headers,
        /**
         * Request timeout in milliseconds.
         */
        public ?int $timeout_ms,
        /**
         * Whether the server is currently enabled.
         */
        public bool $is_enabled,
        /**
         * Number of tools discovered on the server.
         */
        public ?int $catalog_tools_count,
        /**
         * When the server was last called.
         */
        public ?CarbonInterface $last_invoked_at,
        /**
         * How many times the server has been called.
         */
        public int $invocation_count,
        /**
         * When the server was added.
         */
        public ?CarbonInterface $created_at,
    ) {}

    public static function fromModel(AgentMcpServer $server): self
    {
        return new self(
            id: $server->external_id,
            name: $server->name,
            slug: $server->slug,
            url: $server->url,
            transport: McpTransport::from($server->transport),
            auth_type: McpAuthType::from($server->auth_type),
            has_auth_value: $server->auth_value !== null,
            has_headers: $server->headers !== null && $server->headers !== [],
            timeout_ms: $server->timeout_ms,
            is_enabled: (bool) $server->is_enabled,
            catalog_tools_count: $server->catalog_tools_count,
            last_invoked_at: $server->last_invoked_at,
            invocation_count: (int) $server->invocation_count,
            created_at: $server->created_at,
        );
    }
}
