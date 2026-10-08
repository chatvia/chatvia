<?php

namespace Domain\Integration\Mcp\Exceptions;

use RuntimeException;

/**
 * Raised when a per-agent connector setting is changed for a connector that
 * is not attached to that agent.
 */
class ConnectorNotAttachedException extends RuntimeException
{
    public static function toAgent(string $serverSlug, int $agentId): self
    {
        return new self("Connector \"{$serverSlug}\" is not attached to agent {$agentId}.");
    }
}
