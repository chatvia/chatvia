<?php

namespace Domain\Conversation\Services\Tooling\Routing;

/**
 * Decides whether a tool name belongs to an MCP server (prefixed
 * `mcp__{slug}__...`) or to a webhook-backed AgentFunction (any other
 * shape). Pure string inspection — no DB access.
 */
class ToolRouter
{
    public const MCP_PREFIX = 'mcp__';

    public function isMcp(string $name): bool
    {
        if (! str_starts_with($name, self::MCP_PREFIX)) {
            return false;
        }

        // Require `mcp__<slug>__<tool>` shape, not just `mcp__foo`.
        return str_contains(substr($name, strlen(self::MCP_PREFIX)), '__');
    }
}
