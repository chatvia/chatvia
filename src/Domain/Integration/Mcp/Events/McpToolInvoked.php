<?php

namespace Domain\Integration\Mcp\Events;

use DateTimeInterface;
use Illuminate\Foundation\Events\Dispatchable;

/**
 * An MCP tool was invoked by an agent. Keyed by (server slug, tool name) and
 * carries the label fields so the analytics rollup survives tool/server
 * deletion and catalog re-syncs.
 *
 * Fired by McpToolDispatcher; queued listeners update the live invocation
 * counters (UpdateMcpInvocationCounters) and the analytics rollup
 * (RecordAnalytics).
 */
class McpToolInvoked
{
    use Dispatchable;

    public function __construct(
        public readonly int $agentId,
        public readonly int $workspaceId,
        public readonly int $serverId,
        public readonly string $serverSlug,
        public readonly string $toolName,
        public readonly ?string $toolDescription,
        public readonly string $serverName,
        public readonly ?string $connectorSlug,
        public readonly ?string $error,
        public readonly DateTimeInterface $occurredAt,
    ) {}
}
