<?php

namespace Domain\Integration\Mcp\Exceptions;

use RuntimeException;

/**
 * Raised when a tool id does not belong to the given MCP server (so the
 * caller can answer 404 instead of touching another server's tool).
 */
class McpToolNotFoundException extends RuntimeException
{
    public static function onServer(int $toolId, string $serverSlug): self
    {
        return new self("Tool {$toolId} not found on connector \"{$serverSlug}\".");
    }
}
