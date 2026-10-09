<?php

namespace Domain\Integration\Mcp\Contracts;

use Domain\Integration\Mcp\Data\McpToolDefinitionData;

/**
 * Reads the tool definitions of one of Chatvia's own, code-defined MCP
 * servers without an HTTP round-trip, so the deploy-time builtin sync always
 * reflects the release being deployed. The implementation lives with the MCP
 * server adapters (App\Mcp); the domain only sees the definitions.
 */
interface BuiltinToolDefinitions
{
    /**
     * @param  class-string  $serverClass  A server class listed in config/mcp.php.
     * @return list<McpToolDefinitionData>
     */
    public function forServer(string $serverClass): array;
}
