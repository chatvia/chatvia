<?php

namespace Domain\Conversation\Services\Tooling\Registry;

use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Enums\McpToolScope;
use Domain\Integration\Mcp\Models\McpTool;
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
    private const CACHE_TTL = 3600;

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
     * through the `mcp_server_tool` membership). Used by the cache + fallback
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
        $tools = McpTool::query()
            ->select(['mcp_tools.*', 'mcp_servers.slug as server_slug'])
            ->join('mcp_servers', 'mcp_servers.id', '=', 'mcp_tools.mcp_server_id')
            ->leftJoin('agent_mcp_server', function ($join) use ($agentId): void {
                $join->on('agent_mcp_server.mcp_server_id', '=', 'mcp_servers.id')
                    ->where('agent_mcp_server.agent_id', $agentId);
            })
            ->where('mcp_servers.is_enabled', true)
            ->where('mcp_servers.uses_central_catalog', false)
            ->where('mcp_tools.permission', '!=', 'blocked')
            // Only tools attached to THIS agent (via the agent_mcp_server pivot).
            ->whereNotNull('agent_mcp_server.agent_id')
            ->orderBy('mcp_servers.slug')
            ->orderBy('mcp_tools.name')
            ->get();

        return $tools
            ->map(fn (McpTool $tool): ToolEntry => new ToolEntry(
                kind: 'mcp',
                sourceId: 'mcp_tool:'.$tool->id,
                name: 'mcp__'.$tool->getAttribute('server_slug').'__'.$tool->name,
                description: $tool->description,
                parameters: $tool->input_schema ?? [],
                embedding: null,
                mutates: $tool->scope !== McpToolScope::Read,
            ))
            ->all();
    }

    /**
     * Preset/builtin servers — the shared `connector_tools` catalog, scoped to
     * this agent through the `mcp_server_tool` membership (permission taken from
     * the membership row, not the catalog).
     *
     * @return array<int, ToolEntry>
     */
    private function compilePresetMcpTools(int $agentId): array
    {
        $rows = DB::table('connector_tools as ct')
            ->select(['ct.id as connector_tool_id', 'ct.name', 'ct.description', 'ct.input_schema', 'ct.scope', 'mcp_servers.slug as server_slug'])
            ->join('mcp_server_tool as mst', 'mst.connector_tool_id', '=', 'ct.id')
            ->join('mcp_servers', 'mcp_servers.id', '=', 'mst.mcp_server_id')
            ->leftJoin('agent_mcp_server', function ($join) use ($agentId): void {
                $join->on('agent_mcp_server.mcp_server_id', '=', 'mcp_servers.id')
                    ->where('agent_mcp_server.agent_id', $agentId);
            })
            ->where('mcp_servers.is_enabled', true)
            ->where('mst.permission', '!=', 'blocked')
            // Only tools attached to THIS agent (via the agent_mcp_server pivot).
            ->whereNotNull('agent_mcp_server.agent_id')
            ->orderBy('mcp_servers.slug')
            ->orderBy('ct.name')
            ->get();

        return $rows
            ->map(function (object $row): ToolEntry {
                $schema = is_string($row->input_schema) ? json_decode($row->input_schema, true) : $row->input_schema;

                return new ToolEntry(
                    kind: 'mcp',
                    sourceId: 'connector_tool:'.$row->connector_tool_id,
                    name: 'mcp__'.$row->server_slug.'__'.$row->name,
                    description: (string) $row->description,
                    parameters: is_array($schema) ? $schema : [],
                    embedding: null,
                    mutates: $row->scope !== 'read',
                );
            })
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
        $functions = AgentFunction::query()
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
                kind: 'function',
                sourceId: 'function:'.$fn->id,
                name: $fn->internal_name,
                description: $fn->description,
                parameters: $parameters,
                embedding: null,
                mutates: strtoupper($fn->webhook_method) !== 'GET',
            );
        })->all();
    }

    private function cacheKey(int $agentId): string
    {
        return 'agent:tools:'.$agentId;
    }
}
