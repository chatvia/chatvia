<?php

namespace Domain\Integration\Mcp\Services;

use Domain\Conversation\Jobs\EmbedMcpToolsJob;
use Domain\Integration\Connector\Services\ConnectorCatalog;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Enums\McpToolScope;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\McpTool;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Manages the per-server tool catalog by syncing the MCP server's
 * `tools/list` response into the `mcp_tools` table. Replaces the
 * previous Redis-cached "compile to OpenAI shape" path — tools are
 * now first-class rows that ToolRegistry queries directly, with
 * embeddings populated asynchronously.
 */
class McpCatalog
{
    public function __construct(
        private readonly McpClient $client,
    ) {}

    /**
     * Fetch the tool list from the MCP server and reconcile `mcp_tools`
     * with it: upsert each returned tool, then delete rows for tools the
     * server no longer exposes. Dispatches an embedding job for any rows
     * whose embeddings are missing or stale.
     *
     * Throws on fetch failure — callers (Connect / Refresh flows) need
     * to surface the error to the user.
     *
     * @return Collection<int, McpTool>
     */
    public function refresh(AgentMcpServer $server): Collection
    {
        if (! $server->is_enabled) {
            return new Collection;
        }

        // Backfill the connector's description from its catalog preset. It feeds
        // the tool embeddings (server name + description + tool name +
        // description), so existing tools are re-embedded when it first lands.
        if (($server->description === null || $server->description === '') && $server->connector_slug) {
            $preset = app(ConnectorCatalog::class)->find($server->connector_slug);
            if ($preset && $preset->description !== '') {
                $server->forceFill(['description' => $preset->description])->save();

                McpTool::query()
                    ->where('mcp_server_id', $server->id)
                    ->update(['embedding' => null, 'embedding_bm25' => null]);
            }
        }

        try {
            $rawTools = $this->client->fetchTools($server);
        } catch (Throwable $e) {
            Log::warning('MCP catalog fetch failed', [
                'server_id' => $server->id,
                'slug' => $server->slug,
                'error' => $e->getMessage(),
            ]);

            $server->forceFill(['last_error' => $e->getMessage()])->save();

            throw $e;
        }

        $now = now();
        $validatedNames = [];

        DB::transaction(function () use ($server, $rawTools, $now, &$validatedNames): void {
            foreach ($rawTools as $tool) {
                if (! is_array($tool) || ! isset($tool['name']) || ! is_string($tool['name'])) {
                    continue;
                }

                // The compiled tool name (`mcp__{slug}__{name}`) must satisfy
                // OpenAI's tool-name regex when ToolRegistry reapplies the
                // prefix. Skip names that won't survive that step.
                $prefixed = $server->toolPrefix().$tool['name'];
                if (! preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,63}$/', $prefixed)) {
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

                // Seed the per-tool permission from scope on first sight only —
                // a re-sync must never clobber a user's choice.
                // Read → Allow; Write → Approval.
                if (! $row->exists) {
                    $row->permission = $scope === McpToolScope::Read
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
                    // Scope re-syncs every refresh — it's a fact about the tool,
                    // not a user choice. If the server upgrades a read tool to a
                    // write tool, the section split should follow.
                    'scope' => $scope,
                    'fetched_at' => $now,
                ])->save();
            }

            // Drop rows the server stopped advertising. Empty-set case
            // becomes "delete all for this server" without a NOT IN ().
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

        // Embedding refresh runs out-of-band. The job re-embeds any row
        // whose `embedding` or `embedding_bm25` is NULL — the catalog
        // upsert leaves both columns alone, so only newly inserted rows
        // get picked up here today.
        EmbedMcpToolsJob::dispatch($server->id);

        return $server->tools()->orderBy('name')->get();
    }
}
