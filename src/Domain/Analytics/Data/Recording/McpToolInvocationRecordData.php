<?php

namespace Domain\Analytics\Data\Recording;

use DateTimeInterface;
use Domain\Integration\Mcp\Events\McpToolInvoked;
use Spatie\LaravelData\Data;

/**
 * One MCP tool call by an agent, keyed by server slug and tool name, with the
 * labels captured at call time. Built from a {@see McpToolInvoked} event.
 */
class McpToolInvocationRecordData extends Data
{
    public function __construct(
        public int $agentId,
        public int $workspaceId,
        public string $serverSlug,
        public string $toolName,
        public ?string $toolDescription,
        public string $serverName,
        public ?string $connectorSlug,
        public DateTimeInterface $occurredAt,
    ) {}

    public static function fromEvent(McpToolInvoked $event): self
    {
        return new self(
            agentId: $event->agentId,
            workspaceId: $event->workspaceId,
            serverSlug: $event->serverSlug,
            toolName: $event->toolName,
            toolDescription: $event->toolDescription,
            serverName: $event->serverName,
            connectorSlug: $event->connectorSlug,
            occurredAt: $event->occurredAt,
        );
    }
}
