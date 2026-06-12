<?php

namespace Domain\Integration\Mcp\Services;

use Domain\Conversation\Jobs\EmbedConnectorToolsJob;
use Domain\Conversation\Jobs\EmbedMcpToolsJob;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Enums\McpToolScope;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Mcp\Models\McpTool;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Reconciles a server's tool catalog from its `tools/list` response.
 *
 * Two storage shapes; refresh() re-derives the discriminator every sync and
 * persists it to `mcp_servers.uses_central_catalog` so the raw-SQL read paths
 * (ToolRegistry, ToolHybridSearch) follow the same branch:
 *
 *   - CENTRAL (preset/builtin fetched from the connector's CANONICAL URL) →
 *     the shared `connector_tools` catalog (one row per connector_slug+name,
 *     embedded once) plus a thin per-workspace `mcp_server_tool` membership
 *     carrying that workspace's permission. So leads/stripe/… are stored once
 *     no matter how many workspaces connect them.
 *   - PER-SERVER (custom servers, and presets whose URL is workspace-supplied
 *     — requires_url / BYO-OAuth) → rows in `mcp_tools`, each with its own
 *     embeddings. Tenant-specific definitions must never enter the shared
 *     catalog: one workspace's server would otherwise write the tool text
 *     every other workspace's agents put in their prompts.
 *
 * Throws on fetch failure — callers (Connect / Refresh flows) surface the error.
 */
class McpCatalog
{
    public function __construct(
        private readonly McpClient $client,
    ) {}

    /**
     * @return Collection<int, ConnectorTool>|Collection<int, McpTool>
     */
    public function refresh(AgentMcpServer $server): Collection
    {
        if (! $server->is_enabled) {
            return new Collection;
        }

        // Backfill the connector's display description from its catalog preset.
        // Per-server-shaped rows embed the server description, so their tools
        // re-embed when it first lands.
        if (($server->description === null || $server->description === '') && $server->connector_slug) {
            $preset = app(ConnectorCatalog::class)->find($server->connector_slug);
            if ($preset && $preset->description !== '') {
                $server->forceFill(['description' => $preset->description])->save();

                McpTool::query()
                    ->where('mcp_server_id', $server->id)
                    ->update(['embedding' => null, 'embedding_bm25' => null]);
            }
        }

        // Re-derive the storage shape every sync and persist it, so the SQL
        // read paths follow this exact branch. Only definitions fetched from
        // the connector's canonical URL may enter the shared catalog.
        $central = AgentMcpServer::shouldUseCentralCatalog($server->connector_slug, $server->url);
        if ($server->uses_central_catalog !== $central) {
            $server->forceFill(['uses_central_catalog' => $central])->save();
        }

        return $central
            ? $this->refreshPreset($server)
            : $this->refreshCustom($server);
    }

    /**
     * Canonical-URL preset/builtin: upsert the shared definition into
     * `connector_tools`, then reconcile this server's `mcp_server_tool`
     * membership.
     *
     * @return Collection<int, ConnectorTool>
     */
    private function refreshPreset(AgentMcpServer $server): Collection
    {
        $rawTools = $this->fetchOrThrow($server);

        $now = now();
        // A REMOTE server's self-reported readOnlyHint is not trustworthy, so
        // only our own built-in servers (internal URL) auto-Allow read tools;
        // every third-party tool defaults to Approval (human-in-the-loop).
        // Derived from the CANONICAL connector URL — never the row's, so a
        // workspace-influenced URL can't promote a shared default. (refresh()
        // guarantees they match here; this keeps the trust source explicit.)
        $canonical = (string) AgentMcpServer::canonicalConnectorUrl((string) $server->connector_slug);
        $isInternal = InternalMcpSignature::isInternalUrl($canonical);
        $validatedToolIds = [];

        DB::transaction(function () use ($server, $rawTools, $now, $isInternal, &$validatedToolIds): void {
            foreach ($rawTools as $tool) {
                if (! is_array($tool) || ! isset($tool['name']) || ! is_string($tool['name'])) {
                    continue;
                }

                // The compiled name (`mcp__{slug}__{name}`) must satisfy OpenAI's
                // tool-name regex when ToolRegistry reapplies the prefix.
                if (! preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,63}$/', $server->toolPrefix().$tool['name'])) {
                    continue;
                }

                $annotations = is_array($tool['annotations'] ?? null) ? $tool['annotations'] : null;
                $description = (string) ($tool['description'] ?? '');
                $scope = ($annotations['readOnlyHint'] ?? false) === true
                    ? McpToolScope::Read
                    : McpToolScope::Write;
                $defaultPermission = ($isInternal && $scope === McpToolScope::Read)
                    ? McpToolPermission::Allow
                    : McpToolPermission::Approval;

                $attrs = [
                    'description' => $description,
                    'input_schema' => is_array($tool['inputSchema'] ?? null) ? $tool['inputSchema'] : ['type' => 'object', 'properties' => (object) []],
                    'annotations' => $annotations,
                    // Scope + default re-sync every refresh — facts about the tool,
                    // not user choices (which live on the membership row).
                    'scope' => $scope,
                    'default_permission' => $defaultPermission,
                    'fetched_at' => $now,
                ];

                // Shared catalog row, race-safe by (connector_slug, name): two
                // workspaces connecting the same connector at once converge on one
                // row. Embeddings stay null on insert (filled out-of-band) and are
                // only re-nulled when the embed-relevant text changes.
                $row = ConnectorTool::query()->firstOrCreate(
                    ['connector_slug' => $server->connector_slug, 'name' => $tool['name']],
                    $attrs,
                );

                if (! $row->wasRecentlyCreated) {
                    if ($row->description !== $description) {
                        $row->embedding = null;
                        $row->embedding_bm25 = null;
                    }
                    $row->fill($attrs);
                    if ($row->isDirty()) {
                        $row->save();
                    }
                }

                $validatedToolIds[] = $row->id;

                // Per-workspace membership — seed permission from the catalog
                // default on FIRST sight only; a re-sync must never clobber the
                // user's toggle or the counters (DO NOTHING on conflict).
                DB::table('mcp_server_tool')->insertOrIgnore([
                    'mcp_server_id' => $server->id,
                    'connector_tool_id' => $row->id,
                    'permission' => $row->default_permission->value,
                ]);
            }

            // Drop memberships for tools the server stopped advertising. Empty set
            // → delete all (mirrors the custom path's name-based reconcile).
            $query = DB::table('mcp_server_tool')->where('mcp_server_id', $server->id);
            if ($validatedToolIds !== []) {
                $query->whereNotIn('connector_tool_id', $validatedToolIds);
            }
            $query->delete();

            // Shape hygiene: the server may have flipped per-server → central
            // (e.g. its URL became canonical again). Stale local rows would
            // double-surface its tools in the custom read channels.
            McpTool::query()->where('mcp_server_id', $server->id)->delete();
        });

        $server->forceFill([
            'catalog_cached_at' => $now,
            'catalog_tools_count' => count($validatedToolIds),
            'last_error' => null,
        ])->save();

        // Catalog rows nothing references anymore (tool removed upstream for
        // every workspace) would otherwise live in the shared embedding
        // indexes forever.
        ConnectorTool::pruneOrphaned((string) $server->connector_slug);

        // Embed the shared catalog once per connector_slug — the job no-ops for
        // rows already embedded, so the second workspace to connect pays nothing.
        EmbedConnectorToolsJob::dispatch((string) $server->connector_slug);

        return $server->connectorTools()->orderBy('name')->get();
    }

    /**
     * Per-server shape (custom servers, and presets with a workspace-supplied
     * URL): rows in `mcp_tools`, each with its own embeddings. Unchanged from
     * the original single-table design.
     *
     * @return Collection<int, McpTool>
     */
    private function refreshCustom(AgentMcpServer $server): Collection
    {
        $rawTools = $this->fetchOrThrow($server);

        $now = now();
        $validatedNames = [];

        DB::transaction(function () use ($server, $rawTools, $now, &$validatedNames): void {
            // Shape hygiene: the server may have flipped central → per-server
            // (e.g. a preset's canonical URL changed). Stale memberships would
            // keep serving the old shared definitions alongside the new rows.
            DB::table('mcp_server_tool')->where('mcp_server_id', $server->id)->delete();

            foreach ($rawTools as $tool) {
                if (! is_array($tool) || ! isset($tool['name']) || ! is_string($tool['name'])) {
                    continue;
                }

                if (! preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,63}$/', $server->toolPrefix().$tool['name'])) {
                    continue;
                }

                $validatedNames[] = $tool['name'];

                $annotations = is_array($tool['annotations'] ?? null) ? $tool['annotations'] : null;
                $description = (string) ($tool['description'] ?? '');
                $scope = ($annotations['readOnlyHint'] ?? false) === true
                    ? McpToolScope::Read
                    : McpToolScope::Write;

                $row = McpTool::query()->firstOrNew([
                    'mcp_server_id' => $server->id,
                    'name' => $tool['name'],
                ]);

                // Seed the per-tool permission on first sight only — a re-sync must
                // never clobber a user's choice. A REMOTE server's self-reported
                // readOnlyHint is not trustworthy, so only an internal URL (one of
                // Chatvia's own servers) auto-Allows read tools; everything else
                // defaults to Approval (human-in-the-loop).
                if (! $row->exists) {
                    $isBuiltin = InternalMcpSignature::isInternalUrl((string) $server->url);

                    $row->permission = ($isBuiltin && $scope === McpToolScope::Read)
                        ? McpToolPermission::Allow
                        : McpToolPermission::Approval;
                }

                // Re-embed when the embed-relevant text (description) changes.
                if ($row->exists && $row->description !== $description) {
                    $row->embedding = null;
                    $row->embedding_bm25 = null;
                }

                $row->forceFill([
                    'description' => $description,
                    'input_schema' => is_array($tool['inputSchema'] ?? null) ? $tool['inputSchema'] : ['type' => 'object', 'properties' => (object) []],
                    'annotations' => $annotations,
                    'scope' => $scope,
                    'fetched_at' => $now,
                ])->save();
            }

            $query = McpTool::query()->where('mcp_server_id', $server->id);
            if ($validatedNames !== []) {
                $query->whereNotIn('name', $validatedNames);
            }
            $query->delete();
        });

        $server->forceFill([
            'catalog_cached_at' => $now,
            'catalog_tools_count' => count($validatedNames),
            'last_error' => null,
        ])->save();

        EmbedMcpToolsJob::dispatch($server->id);

        return $server->tools()->orderBy('name')->get();
    }

    /**
     * @return array<int, mixed>
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

            $server->forceFill(['last_error' => $e->getMessage()])->save();

            throw $e;
        }
    }
}
