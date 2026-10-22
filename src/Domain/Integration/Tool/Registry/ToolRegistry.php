<?php

namespace Domain\Integration\Tool\Registry;

use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Enums\McpToolScope;
use Domain\Integration\Mcp\Models\McpTool;
use Domain\Integration\Tool\Contracts\AttachableToAgents;
use Domain\Integration\Tool\Data\ToolEntry;
use Domain\Integration\Tool\Data\ToolName;
use Domain\Integration\Tool\Enums\ToolKind;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Pool catalog for an agent's tools — the full attached pool (MCP +
 * webhook functions) as `ToolEntry[]`, cached for an hour. Used by the
 * fallback path in `ToolSelector` (when query embedding fails).
 *
 * Per-turn ranked retrieval is **not** here — see `ToolHybridSearch`,
 * which does dense + BM25 hybrid across both `mcp_tools` and
 * `functions` in a single RRF query.
 */
class ToolRegistry
{
    private const int CACHE_TTL = 3600;

    /**
     * The cache holds serialized ToolEntry objects, so the prefix carries a
     * version: bump it whenever ToolEntry's class name or properties change,
     * or an old cached pool would unserialize into the wrong shape.
     */
    private const string CACHE_PREFIX = 'agent:tools:v2:';

    /**
     * Returns the agent's tool pool as ToolEntry instances. Cached for
     * an hour and invalidated by observers on relevant pivot changes.
     *
     * @return array<int, ToolEntry>
     */
    public function for(Agent $agent): array
    {
        $cacheKey = $this->cacheKey($agent->id);
        $cacheHit = Cache::has($cacheKey);

        $pool = Cache::remember(
            $cacheKey,
            self::CACHE_TTL,
            fn (): array => $this->compile($agent->id),
        );

        Log::debug('ToolRegistry: pool resolved', [
            'agent_id' => $agent->id,
            'source' => $cacheHit ? 'cache' : 'db',
            'pool_size' => count($pool),
        ]);

        return $pool;
    }

    public function forget(int $agentId): void
    {
        Cache::forget($this->cacheKey($agentId));
    }

    /**
     * Drop the cached pool of every agent the given tool source (an MCP server
     * or a webhook function) is attached to — one change to it can affect any
     * number of agents.
     */
    public function forgetAttachedTo(AttachableToAgents $source): void
    {
        $this->forgetMany($source->agents()->pluck('agents.id')->all());
    }

    /**
     * @param  iterable<int|string>  $agentIds
     */
    public function forgetMany(iterable $agentIds): void
    {
        foreach ($agentIds as $agentId) {
            $this->forget((int) $agentId);
        }
    }

    /**
     * @return array<int, ToolEntry>
     */
    private function compile(int $agentId): array
    {
        return [
            ...$this->compileMcpTools($agentId),
            ...$this->compileWebhookTools($agentId),
        ];
    }

    /**
     * Full attached-MCP pool, no ranking — the union of CUSTOM tools (per-server
     * `mcp_tools`) and PRESET tools (the shared `connector_tools` catalog reached
     * through the `connector_tool_mcp_server` membership). Used by the cache + fallback
     * path; per-turn ranked retrieval goes through `ToolHybridSearch`.
     *
     * @return array<int, ToolEntry>
     */
    private function compileMcpTools(int $agentId): array
    {
        $entries = [
            ...$this->compileCustomMcpTools($agentId),
            ...$this->compilePresetMcpTools($agentId),
        ];

        // De-dupe a slug present as both an attached row and a global one.
        return Collection::make($entries)
            ->unique(fn (ToolEntry $entry): string => $entry->name)
            ->values()
            ->all();
    }

    /**
     * Per-server-shaped servers — their own `mcp_tools` rows. Covers custom
     * servers AND presets with a workspace-supplied URL (requires_url /
     * BYO-OAuth), which never share the central catalog.
     *
     * @return array<int, ToolEntry>
     */
    private function compileCustomMcpTools(int $agentId): array
    {
        // Only what a ToolEntry needs: the embedding and search text are
        // large and only the ranked search reads them.
        $tools = McpTool::query()
            ->select(['mcp_tools.id', 'mcp_tools.name', 'mcp_tools.description', 'mcp_tools.input_schema', 'mcp_tools.scope', 'mcp_servers.slug as server_slug'])
            ->join('mcp_servers', 'mcp_servers.id', '=', 'mcp_tools.mcp_server_id')
            ->leftJoin('agent_mcp_server', function ($join) use ($agentId): void {
                $join->on('agent_mcp_server.mcp_server_id', '=', 'mcp_servers.id')
                    ->where('agent_mcp_server.agent_id', $agentId);
            })
            ->where('mcp_servers.is_enabled', true)
            ->where('mcp_servers.uses_central_catalog', false)
            ->where('mcp_tools.permission', '!=', McpToolPermission::Blocked->value)
            // Only tools attached to THIS agent (via the agent_mcp_server pivot).
            ->whereNotNull('agent_mcp_server.agent_id')
            ->orderBy('mcp_servers.slug')
            ->orderBy('mcp_tools.name')
            ->get();

        return $tools
            ->map(fn (McpTool $tool): ToolEntry => new ToolEntry(
                kind: ToolKind::Mcp,
                sourceId: 'mcp_tool:'.$tool->id,
                name: ToolName::forMcp((string) $tool->getAttribute('server_slug'), $tool->name)->value,
                description: $tool->description,
                parameters: $tool->input_schema ?? [],
                mutates: $tool->scope !== McpToolScope::Read,
            ))
            ->all();
    }

    /**
     * Preset/builtin servers — the shared `connector_tools` catalog, scoped to
     * this agent through the `connector_tool_mcp_server` membership (permission taken from
     * the membership row, not the catalog). A plain query-builder join rather
     * than relations: one round-trip for the whole pool, without hydrating a
     * model per catalog row and per pivot row.
     *
     * @return array<int, ToolEntry>
     */
    private function compilePresetMcpTools(int $agentId): array
    {
        $rows = DB::table('connector_tools as ct')
            ->select(['ct.id as connector_tool_id', 'ct.name', 'ct.description', 'ct.input_schema', 'ct.scope', 'mcp_servers.slug as server_slug'])
            ->join('connector_tool_mcp_server as mst', 'mst.connector_tool_id', '=', 'ct.id')
            ->join('mcp_servers', 'mcp_servers.id', '=', 'mst.mcp_server_id')
            ->leftJoin('agent_mcp_server', function ($join) use ($agentId): void {
                $join->on('agent_mcp_server.mcp_server_id', '=', 'mcp_servers.id')
                    ->where('agent_mcp_server.agent_id', $agentId);
            })
            ->where('mcp_servers.is_enabled', true)
            ->where('mst.permission', '!=', McpToolPermission::Blocked->value)
            // Only tools attached to THIS agent (via the agent_mcp_server pivot).
            ->whereNotNull('agent_mcp_server.agent_id')
            ->orderBy('mcp_servers.slug')
            ->orderBy('ct.name')
            ->get();

        return $rows
            ->map(fn (object $row): ToolEntry => new ToolEntry(
                kind: ToolKind::Mcp,
                sourceId: 'connector_tool:'.$row->connector_tool_id,
                name: ToolName::forMcp((string) $row->server_slug, (string) $row->name)->value,
                description: (string) $row->description,
                parameters: ToolEntry::decodeSchema($row->input_schema),
                mutates: $row->scope !== McpToolScope::Read->value,
            ))
            ->all();
    }

    /**
     * Webhook functions: each function row IS one tool, so the same
     * `functions.is_enabled` flag covers both server-level and tool-level
     * enable. No second-tier opt-out table needed.
     *
     * @return array<int, ToolEntry>
     */
    private function compileWebhookTools(int $agentId): array
    {
        // Only what a ToolEntry needs, named per table: a bare `*` would also
        // read the large embedding and search text, and the pivot's own
        // invocation_count and last_invoked_at would overwrite the function's.
        $functions = AgentFunction::query()
            ->select(['functions.id', 'functions.internal_name', 'functions.description', 'functions.parameters_schema', 'functions.webhook_method'])
            ->join('agent_function', function ($join) use ($agentId): void {
                $join->on('agent_function.function_id', '=', 'functions.id')
                    ->where('agent_function.agent_id', $agentId);
            })
            ->where('functions.is_enabled', true)
            ->orderBy('functions.internal_name')
            ->get();

        return $functions->map(function (AgentFunction $fn): ToolEntry {
            $schema = $fn->parameters_schema;
            $parameters = $schema === null
                ? []
                : (is_object($schema) && method_exists($schema, 'toArray') ? $schema->toArray() : (array) $schema);

            return new ToolEntry(
                kind: ToolKind::Function,
                sourceId: 'function:'.$fn->id,
                name: $fn->internal_name,
                description: $fn->description,
                parameters: $parameters,
                mutates: $fn->webhook_method->changesData(),
            );
        })->all();
    }

    private function cacheKey(int $agentId): string
    {
        return self::CACHE_PREFIX.$agentId;
    }
}
