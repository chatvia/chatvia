<?php

namespace Domain\Integration\Mcp\Actions\Catalog;

use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Data\McpToolDefinitionData;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Mcp\Models\McpServerTool;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Integration\Mcp\Services\McpClient;
use Domain\Integration\Tool\Jobs\EmbedConnectorToolsJob;
use Domain\Integration\Tool\Jobs\EmbedMcpToolsJob;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Re-reads a server's tools (`tools/list`) and stores them. A server keeps its
 * tools in one of two shapes, re-decided on every sync and saved to
 * `mcp_servers.uses_central_catalog` so the SQL read paths (ToolRegistry,
 * ToolHybridSearch) follow the same branch:
 *
 *   - SHARED (a catalog connector reached at its canonical URL) → the shared
 *     `connector_tools` catalog, stored and embedded once per connector, plus
 *     a per-workspace `mcp_server_tool` membership carrying that workspace's
 *     permission.
 *   - OWN (custom servers, and presets whose URL the workspace supplied) →
 *     rows in `mcp_tools`, each with its own embeddings. Tenant-specific
 *     definitions must never enter the shared catalog, or one workspace's
 *     server could write the tool text other workspaces' agents read.
 *
 * Throws when the server cannot be reached; the error is also kept on the
 * row so the dashboard can show it.
 */
class SyncMcpServerCatalogAction
{
    public function __construct(
        private readonly McpClient $client,
        private readonly ConnectorCatalog $catalog,
        private readonly UpsertConnectorToolsAction $upsertConnectorTools,
        private readonly ReconcileToolMembershipsAction $reconcileMemberships,
    ) {}

    /**
     * @return Collection<int, ConnectorTool>|Collection<int, McpTool> The server's tools after the sync.
     */
    public function execute(AgentMcpServer $server): Collection
    {
        if (! $server->is_enabled) {
            return new Collection;
        }

        $this->backfillDescription($server);

        $shared = $this->catalog->sharesCentralCatalog($server->connector_slug, $server->url);
        if ($server->uses_central_catalog !== $shared) {
            $server->forceFill(['uses_central_catalog' => $shared])->save();
        }

        $tools = array_values(array_filter(
            $this->fetchOrThrow($server),
            fn (McpToolDefinitionData $tool): bool => $tool->fitsToolNameFor((string) $server->slug),
        ));

        return $shared
            ? $this->syncSharedCatalog($server, $tools)
            : $this->syncOwnCatalog($server, $tools);
    }

    /**
     * A connector's display description comes from its catalog preset. Tools
     * stored per server embed that description, so they re-embed once it
     * first lands.
     */
    private function backfillDescription(AgentMcpServer $server): void
    {
        if (filled($server->description) || ! $server->connector_slug) {
            return;
        }

        $preset = $this->catalog->find($server->connector_slug);
        if (! $preset instanceof ConnectorData || $preset->description === '') {
            return;
        }

        $server->forceFill(['description' => $preset->description])->save();

        McpTool::query()
            ->where('mcp_server_id', $server->id)
            ->update(['embedding' => null, 'search_text' => null]);
    }

    /**
     * @param  list<McpToolDefinitionData>  $definitions
     * @return Collection<int, ConnectorTool>
     */
    private function syncSharedCatalog(AgentMcpServer $server, array $definitions): Collection
    {
        $connectorSlug = (string) $server->connector_slug;

        $tools = DB::transaction(function () use ($server, $connectorSlug, $definitions): Collection {
            $tools = $this->upsertConnectorTools->execute($connectorSlug, $definitions);
            $this->reconcileMemberships->execute($server, $tools);

            // The server may have switched from its own rows to the shared
            // catalog; stale own rows would surface its tools twice.
            McpTool::query()->where('mcp_server_id', $server->id)->delete();

            return $tools;
        });

        $server->markCatalogSynced($tools->count());

        // Catalog rows no server references any more (a tool removed upstream
        // for every workspace) would otherwise stay in the shared indexes.
        ConnectorTool::pruneOrphaned($connectorSlug);

        // Embedding runs once per connector — the job skips rows that already
        // have embeddings, so the second workspace to connect pays nothing.
        EmbedConnectorToolsJob::dispatch($connectorSlug);

        return $server->connectorTools()->orderBy('name')->get();
    }

    /**
     * @param  list<McpToolDefinitionData>  $definitions
     * @return Collection<int, McpTool>
     */
    private function syncOwnCatalog(AgentMcpServer $server, array $definitions): Collection
    {
        DB::transaction(function () use ($server, $definitions): void {
            // The server may have switched from the shared catalog to its own
            // rows; stale memberships would keep serving the old definitions.
            McpServerTool::query()->where('mcp_server_id', $server->id)->delete();

            foreach ($definitions as $definition) {
                $this->storeOwnTool($server, $definition);
            }

            McpTool::query()
                ->where('mcp_server_id', $server->id)
                ->when($definitions !== [], fn ($query) => $query->whereNotIn(
                    'name',
                    array_map(fn (McpToolDefinitionData $tool): string => $tool->name, $definitions),
                ))
                ->delete();
        });

        $server->markCatalogSynced(count($definitions));

        EmbedMcpToolsJob::dispatch($server->id);

        return $server->tools()->orderBy('name')->get();
    }

    /**
     * The permission is seeded on first sight only — a re-sync never
     * overwrites the workspace's choice. A changed description re-embeds.
     */
    private function storeOwnTool(AgentMcpServer $server, McpToolDefinitionData $definition): void
    {
        $tool = McpTool::query()->firstOrNew([
            'mcp_server_id' => $server->id,
            'name' => $definition->name,
        ]);

        if (! $tool->exists) {
            $tool->permission = $definition->defaultPermission();
        } elseif ($tool->description !== $definition->description) {
            $tool->embedding = null;
            $tool->search_text = null;
        }

        $tool->forceFill([
            'description' => $definition->description,
            'input_schema' => $definition->inputSchema,
            'annotations' => $definition->annotations,
            'scope' => $definition->scope(),
            'fetched_at' => now(),
        ])->save();
    }

    /**
     * @return list<McpToolDefinitionData>
     */
    private function fetchOrThrow(AgentMcpServer $server): array
    {
        try {
            return $this->client->fetchTools($server);
        } catch (Throwable $e) {
            Log::warning('MCP catalog fetch failed', [
                'server_id' => $server->id,
                'slug' => $server->slug,
                'error' => $e->getMessage(),
            ]);

            $server->recordError($e->getMessage());

            throw $e;
        }
    }
}
