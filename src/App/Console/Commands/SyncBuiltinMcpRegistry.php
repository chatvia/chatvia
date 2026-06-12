<?php

namespace App\Console\Commands;

use Domain\Conversation\Actions\Embeddings\EmbedMcpToolsAction;
use Domain\Conversation\Jobs\EmbedConnectorToolsJob;
use Domain\Integration\Mcp\Enums\McpServerCategory;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Enums\McpToolScope;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Server\Tool;
use ReflectionClass;

/**
 * Catalogs Chatvia's built-in MCP servers (config('mcp.builtin')) as GLOBAL
 * (user_id IS NULL) `mcp_servers` rows, syncs their tools into the shared
 * `connector_tools` catalog + a `mcp_server_tool` membership for the global row,
 * then INDEXES (embeds) them — reusing the same catalog tables + embedding action
 * the per-workspace connectors use. Tool definitions are read straight from the
 * code-defined Server classes (reflection + Tool::toArray()), so there is no
 * HTTP round-trip. Idempotent + re-runnable; run on deploy / after tool changes.
 */
#[Description("Catalog + embed Chatvia's built-in MCP servers into the global mcp_servers registry + shared connector_tools catalog.")]
#[Signature('app:sync-builtin-mcp
        {--queue : Defer embedding to the queue (EmbedConnectorToolsJob) instead of running it inline}
        {--fresh : Re-embed every tool, not only ones whose text changed}')]
class SyncBuiltinMcpRegistry extends Command
{
    public function handle(EmbedMcpToolsAction $embed): int
    {
        $now = now();
        $base = rtrim((string) config('app.url'), '/');

        foreach ((array) config('mcp.builtin', []) as $entry) {
            $slug = (string) $entry['slug'];
            $category = McpServerCategory::from((string) $entry['category']);

            $server = AgentMcpServer::query()->whereNull('user_id')->where('slug', $slug)->first()
                ?? new AgentMcpServer;

            $server->forceFill([
                'user_id' => null,
                'category' => $category,
                'connector_slug' => $slug,
                // Builtins always live at their canonical internal URL, so they
                // share the central catalog by construction.
                'uses_central_catalog' => true,
                'name' => (string) $entry['name'],
                'description' => (string) $entry['description'],
                'slug' => $slug,
                'url' => $base.'/mcp/'.$slug,
                'transport' => 'streamable_http',
                'auth_type' => 'none',
                'is_enabled' => true,
            ])->save();

            $count = $this->syncTools($server, (string) $entry['class'], $now);

            if ($this->option('fresh')) {
                ConnectorTool::query()->where('connector_slug', $slug)
                    ->update(['embedding' => null, 'embedding_bm25' => null]);
            }

            if ($this->option('queue')) {
                EmbedConnectorToolsJob::dispatch($slug);
                $this->line(sprintf('  %-18s (%s): %d tools — embedding queued', $slug, $category->value, $count));
            } else {
                $embed->executeForConnector($slug);
                $this->line(sprintf('  %-18s (%s): %d tools — indexed', $slug, $category->value, $count));
            }
        }

        $this->info('Built-in MCP registry synced.');

        return self::SUCCESS;
    }

    /**
     * Upsert one server's tools into the shared `connector_tools` catalog +
     * reconcile the global server's `mcp_server_tool` membership, by introspecting
     * its code-defined Tool classes — mirrors McpCatalog::refresh()'s preset half,
     * without HTTP.
     */
    private function syncTools(AgentMcpServer $server, string $serverClass, Carbon $now): int
    {
        $toolClasses = (new ReflectionClass($serverClass))->getDefaultProperties()['tools'] ?? [];
        $validatedIds = [];

        DB::transaction(function () use ($server, $toolClasses, $now, &$validatedIds): void {
            foreach ((array) $toolClasses as $toolClass) {
                /** @var Tool $tool */
                $tool = app($toolClass);
                $arr = $tool->toArray();

                $name = $arr['name'] ?? null;
                if (! is_string($name) || $name === '') {
                    continue;
                }

                if (! preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,63}$/', $server->toolPrefix().$name)) {
                    continue;
                }

                $annotations = is_array($arr['annotations'] ?? null) ? $arr['annotations'] : null;
                $description = (string) ($arr['description'] ?? '');
                $scope = ($annotations['readOnlyHint'] ?? false) === true
                    ? McpToolScope::Read
                    : McpToolScope::Write;
                // Built-in tools live on internal URLs: read-only auto-Allows.
                $defaultPermission = $scope === McpToolScope::Read
                    ? McpToolPermission::Allow
                    : McpToolPermission::Approval;

                $attrs = [
                    'description' => $description,
                    'input_schema' => is_array($arr['inputSchema'] ?? null) ? $arr['inputSchema'] : ['type' => 'object', 'properties' => (object) []],
                    'annotations' => $annotations,
                    'scope' => $scope,
                    'default_permission' => $defaultPermission,
                    'fetched_at' => $now,
                ];

                $row = ConnectorTool::query()->firstOrCreate(
                    ['connector_slug' => $server->connector_slug, 'name' => $name],
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

                $validatedIds[] = $row->id;

                DB::table('mcp_server_tool')->insertOrIgnore([
                    'mcp_server_id' => $server->id,
                    'connector_tool_id' => $row->id,
                    'permission' => $row->default_permission->value,
                ]);
            }

            $query = DB::table('mcp_server_tool')->where('mcp_server_id', $server->id);
            if ($validatedIds !== []) {
                $query->whereNotIn('connector_tool_id', $validatedIds);
            }
            $query->delete();
        });

        $server->forceFill([
            'catalog_cached_at' => $now,
            'catalog_tools_count' => count($validatedIds),
            'last_error' => null,
        ])->save();

        // A builtin tool removed from code leaves an orphaned catalog row once
        // its membership is reconciled away — sweep it after the staleness window.
        ConnectorTool::pruneOrphaned((string) $server->connector_slug);

        return count($validatedIds);
    }
}
