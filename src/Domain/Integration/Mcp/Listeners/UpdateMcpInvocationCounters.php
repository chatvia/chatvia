<?php

namespace Domain\Integration\Mcp\Listeners;

use Domain\Integration\Mcp\Events\McpToolInvoked;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

/**
 * Maintains the MCP invocation counters (server row, per-agent server pivot,
 * per-server-tool breakdown) off the request path. The updates are raw
 * `+ 1` statements on purpose: they stay atomic when many tool calls land at
 * once, and need no model loads in this hot queued listener.
 */
class UpdateMcpInvocationCounters implements ShouldQueue
{
    /** Run only after the dispatching transaction has committed. */
    public bool $afterCommit = true;

    // The queue name comes from config, which an attribute cannot read.
    public function viaQueue(): string
    {
        return (string) config('chatvia.analytics.queue', 'analytics');
    }

    public function handle(McpToolInvoked $event): void
    {
        DB::table('mcp_servers')
            ->where('id', $event->serverId)
            ->update([
                'invocation_count' => DB::raw('invocation_count + 1'),
                'last_invoked_at' => $event->occurredAt,
                'last_error' => $event->error,
            ]);

        // Per-agent counter on the server pivot — a summary signal (how often
        // did this agent touch this server in any way) alongside the per-tool
        // breakdown below.
        DB::table('agent_mcp_server')
            ->where('agent_id', $event->agentId)
            ->where('mcp_server_id', $event->serverId)
            ->update([
                'invocation_count' => DB::raw('invocation_count + 1'),
                'last_invoked_at' => $event->occurredAt,
            ]);

        // Per-server-tool counter for PRESET connectors — drives the tool-level
        // signal on the membership row. Custom servers have no per-tool live
        // counter (the durable analytics rollup covers reporting either way).
        // No-ops silently when the membership/catalog hasn't synced the tool yet,
        // so a stale catalog can never break the dispatch path.
        if ($event->connectorSlug !== null) {
            DB::table('connector_tool_mcp_server')
                ->where('mcp_server_id', $event->serverId)
                ->whereIn('connector_tool_id', function ($query) use ($event): void {
                    $query->select('id')
                        ->from('connector_tools')
                        ->where('connector_slug', $event->connectorSlug)
                        ->where('name', $event->toolName);
                })
                ->update([
                    'invocation_count' => DB::raw('invocation_count + 1'),
                    'last_invoked_at' => $event->occurredAt,
                ]);
        }
    }
}
