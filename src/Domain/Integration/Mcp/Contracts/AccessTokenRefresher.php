<?php

namespace Domain\Integration\Mcp\Contracts;

use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * Keeps an OAuth-connected server's access token usable before the MCP
 * client sends a request with it. Lets the MCP transport depend on "a fresh
 * token" without depending on the connector OAuth flow that knows how to get
 * one (which itself syncs catalogs through the MCP client).
 */
interface AccessTokenRefresher
{
    /**
     * Refresh the stored access token when it has expired or is about to; a
     * no-op for servers that are not OAuth-connected or still have time left.
     */
    public function refreshIfStale(AgentMcpServer $server): void;
}
