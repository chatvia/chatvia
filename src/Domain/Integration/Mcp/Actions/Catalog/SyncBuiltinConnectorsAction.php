<?php

namespace Domain\Integration\Mcp\Actions\Catalog;

use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Contracts\BuiltinToolDefinitions;
use Domain\Integration\Mcp\Data\Catalog\BuiltinCatalogSyncResultData;
use Domain\Integration\Mcp\Data\Catalog\SyncBuiltinConnectorsData;
use Domain\Integration\Mcp\Data\Catalog\SyncedBuiltinConnectorData;
use Domain\Integration\Mcp\Data\McpToolDefinitionData;
use Domain\Integration\Mcp\Enums\McpAuthType;
use Domain\Integration\Mcp\Enums\McpServerCategory;
use Domain\Integration\Mcp\Enums\McpTransport;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Tool\Actions\Embedding\EmbedConnectorToolsAction;
use Domain\Integration\Tool\Jobs\EmbedConnectorToolsJob;
use Domain\Integration\Tool\Registry\ToolRegistry;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Catalogs Chatvia's own MCP tools into the shared `connector_tools` catalog,
 * embeds them, and reconciles the `connector_tool_mcp_server` memberships of EVERY
 * server that uses each builtin — the global row and every workspace's row.
 * Runs on deploy (after migrations) and is safe to re-run.
 *
 * Two kinds of builtin are synced (config/mcp.php):
 *
 *  - GLOBAL builtins (`mcp.builtin`: account-analytics, charts) also get a
 *    global (workspace_id IS NULL) server row, so the owner "Ask your data" chat
 *    can route over them.
 *  - CAPABILITY servers (`mcp.capabilities`: forms) are per-agent opt-in and
 *    get no global row, which would leak their tools into that chat.
 *
 * The membership reconcile is what carries a tool newly added in code to
 * workspaces that connected the builtin earlier; otherwise they would only see
 * it after a manual refresh.
 */
class SyncBuiltinConnectorsAction
{
    private const int AGENT_CHUNK_SIZE = 500;

    private const int SERVER_CHUNK_SIZE = 200;

    public function __construct(
        private readonly BuiltinToolDefinitions $definitions,
        private readonly ConnectorCatalog $catalog,
        private readonly UpsertConnectorToolsAction $upsertConnectorTools,
        private readonly ReconcileToolMembershipsAction $reconcileMemberships,
        private readonly EmbedConnectorToolsAction $embed,
        private readonly ToolRegistry $registry,
    ) {}

    public function execute(SyncBuiltinConnectorsData $options): BuiltinCatalogSyncResultData
    {
        $synced = [];

        foreach ((array) config('mcp.builtin', []) as $entry) {
            $category = McpServerCategory::from((string) $entry['category']);
            $this->provisionGlobalServer($entry, $category);

            $synced[] = new SyncedBuiltinConnectorData(
                slug: (string) $entry['slug'],
                offeredAs: $category->value,
                toolsCount: $this->syncConnector((string) $entry['slug'], (string) $entry['class'], $options),
            );
        }

        foreach ((array) config('mcp.capabilities', []) as $entry) {
            $synced[] = new SyncedBuiltinConnectorData(
                slug: (string) $entry['slug'],
                offeredAs: SyncedBuiltinConnectorData::CAPABILITY,
                toolsCount: $this->syncConnector((string) $entry['slug'], (string) $entry['class'], $options),
            );
        }

        return new BuiltinCatalogSyncResultData(
            connectors: $synced,
            flushedAgents: $this->flushAllToolPools(),
        );
    }

    /**
     * The global row the owner analytics chat routes over. Builtins always
     * live at their canonical internal URL, so they share the central catalog
     * by construction.
     *
     * @param  array<string, mixed>  $entry  One `mcp.builtin` config entry.
     */
    private function provisionGlobalServer(array $entry, McpServerCategory $category): void
    {
        $slug = (string) $entry['slug'];

        $server = AgentMcpServer::query()->whereNull('workspace_id')->where('slug', $slug)->first()
            ?? new AgentMcpServer;

        $server->forceFill([
            'workspace_id' => null,
            'category' => $category,
            'connector_slug' => $slug,
            'uses_central_catalog' => true,
            'name' => (string) $entry['name'],
            'description' => (string) $entry['description'],
            'slug' => $slug,
            'url' => $this->catalog->builtinUrl($slug),
            'transport' => McpTransport::StreamableHttp,
            'auth_type' => McpAuthType::None,
            'is_enabled' => true,
        ])->save();
    }

    /**
     * Catalog one builtin's code-defined tools, embed them, then reconcile the
     * memberships of every server using it. Returns the tool count.
     *
     * @param  class-string  $serverClass
     */
    private function syncConnector(string $slug, string $serverClass, SyncBuiltinConnectorsData $options): int
    {
        $definitions = array_values(array_filter(
            $this->definitions->forServer($serverClass),
            fn (McpToolDefinitionData $tool): bool => $tool->fitsToolNameFor($slug),
        ));

        $tools = $this->upsertConnectorTools->execute($slug, $definitions);

        $this->embedCatalog($slug, $options);
        $this->reconcileEveryServer($slug, $tools);

        // A tool removed from code leaves an orphaned catalog row once its
        // memberships are gone — swept after the staleness window.
        ConnectorTool::pruneOrphaned($slug);

        return $tools->count();
    }

    private function embedCatalog(string $slug, SyncBuiltinConnectorsData $options): void
    {
        if ($options->reembedAll) {
            ConnectorTool::query()->where('connector_slug', $slug)
                ->update(['embedding' => null, 'search_text' => null]);
        }

        if ($options->queueEmbedding) {
            EmbedConnectorToolsJob::dispatch($slug);

            return;
        }

        $this->embed->execute($slug);
    }

    /**
     * Every server of the builtin, in id order, {@see SERVER_CHUNK_SIZE} at a
     * time with one transaction per chunk, so an install with many workspaces
     * never holds one long transaction or every server in memory. Only the
     * columns the reconcile and its timestamp update touch are read: never the
     * encrypted credentials.
     *
     * @param  Collection<int, ConnectorTool>  $tools
     */
    private function reconcileEveryServer(string $slug, Collection $tools): void
    {
        AgentMcpServer::query()
            ->select(['id', 'workspace_id', 'connector_slug', 'catalog_cached_at', 'catalog_tools_count', 'last_error'])
            ->where('connector_slug', $slug)
            ->chunkById(self::SERVER_CHUNK_SIZE, function (Collection $servers) use ($tools): void {
                DB::transaction(function () use ($servers, $tools): void {
                    foreach ($servers as $server) {
                        /** @var AgentMcpServer $server */
                        $this->reconcileMemberships->execute($server, $tools);
                        $server->markCatalogSynced($tools->count());
                    }
                });
            });
    }

    /**
     * A catalog change alters what every attached agent can call, and the
     * per-agent pool cache (one hour) is otherwise only cleared by per-agent
     * changes, which a global sync does not make. A sync is rare and each
     * clear is one cache delete, so every agent is cleared.
     */
    private function flushAllToolPools(): int
    {
        $flushed = 0;

        Agent::query()->select('id')->chunkById(self::AGENT_CHUNK_SIZE, function (Collection $agents) use (&$flushed): void {
            $this->registry->forgetMany($agents->modelKeys());
            $flushed += $agents->count();
        });

        return $flushed;
    }
}
