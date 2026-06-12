<?php

namespace Domain\Analytics\Actions;

use Domain\Analytics\Events\McpToolInvoked;
use Illuminate\Support\Facades\DB;

/**
 * Upserts the per-day MCP-tool-invocation rollup. Durable counterpart to the
 * live `mcp_server_tool.invocation_count` counter: keyed by (server slug, tool
 * name) with no FK, so a tool that's since been deleted or re-synced still
 * shows in usage insights, with labels captured at invocation time.
 */
class RecordMcpToolInvocationAction
{
    public function execute(McpToolInvoked $event): void
    {
        $db = DB::connection(config('chatvia.analytics.connection'));
        $day = $event->occurredAt->format('Y-m-d');

        $db->statement(<<<'SQL'
            INSERT INTO analytics_agent_mcp_tool_daily (agent_id, workspace_id, bucket_day, server_slug, tool_name, invocation_count, tool_description, server_name, connector_slug)
            VALUES (?, ?, ?::date, ?, ?, 1, ?, ?, ?)
            ON CONFLICT (agent_id, bucket_day, server_slug, tool_name) DO UPDATE SET
                invocation_count = analytics_agent_mcp_tool_daily.invocation_count + 1,
                tool_description = EXCLUDED.tool_description,
                server_name = EXCLUDED.server_name,
                connector_slug = EXCLUDED.connector_slug
        SQL, [$event->agentId, $event->workspaceId, $day, $event->serverSlug, $event->toolName, $event->toolDescription, $event->serverName, $event->connectorSlug]);
    }
}
