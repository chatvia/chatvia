<?php

namespace Domain\Integration\Mcp\Actions\Catalog;

use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Mcp\Models\McpServerTool;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Makes a server's `connector_tool_mcp_server` memberships match the catalog tools it
 * advertises: a new tool gets a membership seeded with the catalog's default
 * permission, a tool the server stopped advertising loses its membership.
 *
 * An existing membership is never rewritten — it holds the workspace's own
 * permission choice and the tool's invocation counters.
 */
class ReconcileToolMembershipsAction
{
    /**
     * @param  Collection<int, ConnectorTool>  $tools
     */
    public function execute(AgentMcpServer $server, Collection $tools): void
    {
        DB::transaction(function () use ($server, $tools): void {
            // insertOrIgnore = "do nothing on conflict": a re-sync must never
            // clobber the workspace's permission toggle or the counters.
            McpServerTool::query()->insertOrIgnore($tools->map(fn (ConnectorTool $tool): array => [
                'mcp_server_id' => $server->id,
                'connector_tool_id' => $tool->id,
                'permission' => $tool->default_permission->value,
            ])->all());

            // An empty catalog drops every membership.
            McpServerTool::query()
                ->where('mcp_server_id', $server->id)
                ->when($tools->isNotEmpty(), fn ($query) => $query->whereNotIn('connector_tool_id', $tools->modelKeys()))
                ->delete();
        });
    }
}
