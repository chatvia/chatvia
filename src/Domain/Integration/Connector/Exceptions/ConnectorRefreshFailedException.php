<?php

namespace Domain\Integration\Connector\Exceptions;

use Domain\Integration\Mcp\Exceptions\McpException;
use RuntimeException;
use Throwable;

/**
 * Raised when re-fetching a connector's tool catalog fails. The message is
 * safe to show the workspace: an MCP error keeps its own message, anything
 * else becomes a generic one. The original error is kept as `previous`.
 */
class ConnectorRefreshFailedException extends RuntimeException
{
    public static function because(Throwable $cause): self
    {
        return new self(
            $cause instanceof McpException ? $cause->getMessage() : __('Could not refresh the connector.'),
            previous: $cause,
        );
    }
}
