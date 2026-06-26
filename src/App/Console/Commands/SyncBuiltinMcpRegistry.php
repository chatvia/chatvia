<?php

namespace App\Console\Commands;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Actions\Embeddings\EmbedMcpToolsAction;
use Domain\Conversation\Jobs\EmbedConnectorToolsJob;
use Domain\Conversation\Services\Tooling\Registry\ToolRegistry;
use Domain\Integration\Mcp\Enums\McpServerCategory;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Enums\McpToolScope;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Models\ConnectorTool;
use Domain\Integration\Mcp\Services\Servers\FormsServer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Tool;
use ReflectionClass;

/**
 * Catalogs Chatvia's built-in MCP tools into the shared `connector_tools` catalog,
 * embeds them, and reconciles the `mcp_server_tool` memberships of EVERY server
 * that uses each connector_slug — the global registry row AND every per-workspace
 * row. Tool definitions are read straight from the code-defined Server classes
 * (reflection + Tool::toArray()), so there is no HTTP round-trip; it reads the
 * release being deployed, so it is symlink-timing-independent. Idempotent +
 * re-runnable; run on deploy after app:migrate.
 *
 * Two kinds of connector are synced:
 *
 *  - GLOBAL builtins (config('mcp.builtin'): charts, account-analytics) also get a
 *    GLOBAL (user_id IS NULL) `mcp_servers` row, so the owner analytics "Ask your
 *    data" chat can route over them ({@see \Domain\Integration\Mcp\Services\Routing\BuiltinToolRouter}).
 *  - CAPABILITY connectors ({@see self::CAPABILITY_CONNECTORS}: forms) are per-agent
 *    opt-in and get NO global row — a global Builtin-category row would surface
 *    their tools inside the owner analytics chat (it routes over global rows of
 *    category Builtin). They are NOT in config('mcp.builtin') for that reason.
 *
 * The per-workspace membership reconcile is the load-bearing part: an agent's tool
 * reachability is gated by its PER-WORKSPACE server's memberships (see
 * {@see \Domain\Conversation\Services\Tooling\Routing\ToolHybridSearch}), so a tool
 * newly added in code (e.g. `find-forms` joining `render-form`) only reaches
 * already-provisioned workspaces once this runs — the lazy per-workspace HTTP
 * refresh otherwise fires just on first enable / manual Refresh.
 */
#[Description("Catalog + embed Chatvia's built-in MCP tools into the shared connector_tools catalog and reconcile every workspace's tool memberships.")]
#[Signature('app:sync-builtin-mcp
        {--queue : Defer embedding to the queue (EmbedConnectorToolsJob) instead of running it inline}
        {--fresh : Re-embed every tool, not only ones whose text changed}')]
class SyncBuiltinMcpRegistry extends Command
{
    /**
     * Per-agent opt-in capability connectors, synced WITHOUT a global row (keyed by
     * connector_slug → code-defined Server class). Deliberately absent from
     * config('mcp.builtin'): a global row would leak their tools into the owner
     * analytics chat.
     *
     * @var array<string, class-string<Server>>
     */
    private const CAPABILITY_CONNECTORS = [
        'forms' => FormsServer::class,
    ];

    public function handle(EmbedMcpToolsAction $embed, ToolRegistry $registry): int
    {
        $now = now();
        $base = rtrim((string) config('app.url'), '/');

        // Global builtins: provision the ambient global row (for the owner
        // analytics router), then catalog + embed + reconcile like any connector.
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

            $count = $this->syncConnector($slug, (string) $entry['class'], $embed, $now);
            $this->line(sprintf('  %-18s (%s): %d tools — indexed + reconciled', $slug, $category->value, $count));
        }

        // Capability connectors: NO global row — only the shared catalog +
        // embeddings + per-workspace membership reconcile.
        foreach (self::CAPABILITY_CONNECTORS as $slug => $serverClass) {
            $count = $this->syncConnector($slug, $serverClass, $embed, $now);
            $this->line(sprintf('  %-18s (capability): %d tools — indexed + reconciled', $slug, $count));
        }

        // A catalog change alters the shared connector_tools every agent reads
        // through its mcp_server_tool membership, and ToolRegistry::for() caches
        // that pool per agent for an hour — invalidated only by per-agent pivot
        // observers, which a global sync trips for none. Flush all agents so a warm
        // agent doesn't serve a stale pool until the TTL lapses. A sync is rare and
        // the cost is a cheap cache delete per agent.
        $flushed = 0;
        Agent::query()->select('id')->chunkById(500, function ($agents) use ($registry, &$flushed): void {
            foreach ($agents as $agent) {
                $registry->forget($agent->id);
                $flushed++;
            }
        });

        $this->info("Built-in MCP registry synced. Flushed tool-pool cache for {$flushed} agent(s).");

        return self::SUCCESS;
    }

    /**
     * Catalog one connector's code-defined tools into the shared `connector_tools`
     * table, embed them, then reconcile the memberships of EVERY server using this
     * connector_slug. Returns the tool count.
     */
    private function syncConnector(string $slug, string $serverClass, EmbedMcpToolsAction $embed, Carbon $now): int
    {
        $toolPermissions = $this->upsertCatalog($slug, $serverClass, $now);

        if ($this->option('fresh')) {
            ConnectorTool::query()->where('connector_slug', $slug)
                ->update(['embedding' => null, 'embedding_bm25' => null]);
        }

        if ($this->option('queue')) {
            EmbedConnectorToolsJob::dispatch($slug);
        } else {
            $embed->executeForConnector($slug);
        }

        $this->reconcileMemberships($slug, $toolPermissions, $now);

        // A builtin tool removed from code leaves an orphaned catalog row once its
        // memberships are reconciled away — sweep it after the staleness window.
        ConnectorTool::pruneOrphaned($slug);

        return count($toolPermissions);
    }

    /**
     * Upsert one server's tools into the shared `connector_tools` catalog by
     * introspecting its code-defined Tool classes (no HTTP). Embeddings stay null
     * on insert (filled out-of-band) and are re-nulled only when the embed-relevant
     * text (description) changes.
     *
     * @param  class-string<Server>  $serverClass
     * @return array<int, string> connector_tool id => default permission value
     */
    private function upsertCatalog(string $slug, string $serverClass, Carbon $now): array
    {
        $toolClasses = (new ReflectionClass($serverClass))->getDefaultProperties()['tools'] ?? [];

        /** @var array<int, string> $toolPermissions */
        $toolPermissions = [];

        DB::transaction(function () use ($slug, $toolClasses, $now, &$toolPermissions): void {
            foreach ((array) $toolClasses as $toolClass) {
                /** @var Tool $tool */
                $tool = app($toolClass);
                $arr = $tool->toArray();

                $name = $arr['name'] ?? null;
                if (! is_string($name) || $name === '') {
                    continue;
                }

                // The compiled name (`mcp__{slug}__{name}`) must satisfy OpenAI's
                // tool-name regex when ToolRegistry reapplies the prefix.
                if (! preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,63}$/', 'mcp__'.$slug.'__'.$name)) {
                    continue;
                }

                $annotations = is_array($arr['annotations'] ?? null) ? $arr['annotations'] : null;
                $description = (string) ($arr['description'] ?? '');
                $scope = ($annotations['readOnlyHint'] ?? false) === true
                    ? McpToolScope::Read
                    : McpToolScope::Write;
                // Built-in tools live on internal URLs: read-only auto-Allows;
                // write/delete default to Approval (human-in-the-loop).
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
                    ['connector_slug' => $slug, 'name' => $name],
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

                $toolPermissions[$row->id] = $row->default_permission->value;
            }
        });

        return $toolPermissions;
    }

    /**
     * Reconcile the `mcp_server_tool` memberships of EVERY server that uses this
     * connector_slug — the global registry row AND every per-workspace row — to the
     * shared catalog: seed memberships for newly-cataloged tools (DO NOTHING on
     * conflict, so a user's permission toggle is never clobbered) and drop
     * memberships for tools no longer advertised. This is what carries a newly
     * added builtin tool to already-provisioned workspaces.
     *
     * @param  array<int, string>  $toolPermissions  connector_tool id => default permission value
     */
    private function reconcileMemberships(string $slug, array $toolPermissions, Carbon $now): void
    {
        $servers = AgentMcpServer::query()->where('connector_slug', $slug)->get();

        if ($servers->isEmpty()) {
            return;
        }

        $toolIds = array_keys($toolPermissions);

        DB::transaction(function () use ($servers, $toolPermissions, $toolIds, $now): void {
            foreach ($servers as $server) {
                foreach ($toolPermissions as $toolId => $permission) {
                    DB::table('mcp_server_tool')->insertOrIgnore([
                        'mcp_server_id' => $server->id,
                        'connector_tool_id' => $toolId,
                        'permission' => $permission,
                    ]);
                }

                $query = DB::table('mcp_server_tool')->where('mcp_server_id', $server->id);
                if ($toolIds !== []) {
                    $query->whereNotIn('connector_tool_id', $toolIds);
                }
                $query->delete();

                $server->forceFill([
                    'catalog_cached_at' => $now,
                    'catalog_tools_count' => count($toolIds),
                    'last_error' => null,
                ])->save();
            }
        });
    }
}
