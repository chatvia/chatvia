<?php

namespace Domain\Mcp\Services;

use Domain\Chat\Jobs\EmbedMcpToolsJob;
use Domain\Mcp\Models\AgentMcpServer;
use Domain\Mcp\Models\McpTool;
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

                McpTool::query()->updateOrCreate(
                    ['mcp_server_id' => $server->id, 'name' => $tool['name']],
                    [
                        'description' => (string) ($tool['description'] ?? ''),
                        'input_schema' => is_array($tool['inputSchema'] ?? null) ? $tool['inputSchema'] : ['type' => 'object', 'properties' => (object) []],
                        'fetched_at' => $now,
                    ],
                );
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
