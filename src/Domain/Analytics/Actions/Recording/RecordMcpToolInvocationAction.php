<?php

declare(strict_types=1);

namespace Domain\Analytics\Actions\Recording;

use Domain\Analytics\Data\Recording\McpToolInvocationRecordData;
use Domain\Analytics\Services\AnalyticsDatabase;

/**
 * Upserts the per-day MCP-tool-invocation rollup. Durable counterpart to the
 * live `connector_tool_mcp_server.invocation_count` counter: keyed by (server slug, tool
 * name) with no FK, so a tool that's since been deleted or re-synced still
 * shows in usage insights, with labels captured at invocation time.
 */
class RecordMcpToolInvocationAction
{
    public function __construct(
        private readonly AnalyticsDatabase $analytics,
    ) {}

    public function execute(McpToolInvocationRecordData $invocation): void
    {
        $this->analytics->connection()->statement(<<<'SQL'
            INSERT INTO analytics_agent_mcp_tool_daily (agent_id, workspace_id, bucket_day, server_slug, tool_name, invocation_count, tool_description, server_name, connector_slug)
            VALUES (?, ?, ?::date, ?, ?, 1, ?, ?, ?)
            ON CONFLICT (agent_id, bucket_day, server_slug, tool_name) DO UPDATE SET
                invocation_count = analytics_agent_mcp_tool_daily.invocation_count + 1,
                tool_description = EXCLUDED.tool_description,
                server_name = EXCLUDED.server_name,
                connector_slug = EXCLUDED.connector_slug
        SQL, [
            $invocation->agentId,
            $invocation->workspaceId,
            $invocation->occurredAt->format('Y-m-d'),
            $invocation->serverSlug,
            $invocation->toolName,
            $invocation->toolDescription,
            $invocation->serverName,
            $invocation->connectorSlug,
        ]);
    }
}
