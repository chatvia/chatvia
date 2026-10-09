<?php

namespace Domain\Integration\Mcp\Exceptions;

use RuntimeException;

/**
 * Raised by the MCP client and the connector services when talking to an MCP
 * server fails. The message can carry transport detail (URLs, response
 * bodies), so it is meant for the dashboard and error tracking, not for the
 * model. `serverMessage` holds only the error text the MCP server itself
 * reported (a JSON-RPC error), which is no more sensitive than a tool result
 * and may be shown to the model. For an HTTP failure the code is the status.
 */
class McpException extends RuntimeException
{
    public ?string $serverMessage = null;

    public static function reportedByServer(string $message, string $serverMessage): self
    {
        $exception = new self($message);
        $exception->serverMessage = $serverMessage;

        return $exception;
    }
}
