<?php

namespace Domain\Conversation\Services\Tooling\Registry;

use Domain\Agent\Models\Agent;
use Domain\Integration\Function\Models\AgentFunction;
use Domain\Integration\Mcp\Models\McpTool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Pool catalog for an agent's tools — the full attached pool (MCP +
 * webhook functions) as `ToolEntry[]`, cached for an hour. Used by the
 * fallback path in `ToolSelector` (when query embedding fails) and by
 * `asOpenAiTools()` (settings/diagnostics surfaces).
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
     * Convenience: same entries but already flattened to OpenAI shape.
     * Used by the fallback path in ToolSelector when semantic routing
     * fails — ship the whole pool rather than risking blocking the turn.
     *
     * @return array<int, array<string, mixed>>
     */
    public function asOpenAiTools(Agent $agent): array
    {
        return Collection::make($this->for($agent))
            ->map(fn (ToolEntry $entry): array => $entry->toOpenAiFormat())
            ->all();
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
     * Full attached-MCP pool, no ranking. Used by the cache + fallback
     * path; per-turn ranked retrieval goes through `McpToolHybridSearch`.
     *
     * @return array<int, ToolEntry>
     */
    private function compileMcpTools(int $agentId): array
    {
        $tools = McpTool::query()
            ->select(['mcp_tools.*', 'mcp_servers.slug as server_slug'])
            ->join('mcp_servers', 'mcp_servers.id', '=', 'mcp_tools.mcp_server_id')
            ->join('agent_mcp_server', function ($join) use ($agentId): void {
                $join->on('agent_mcp_server.mcp_server_id', '=', 'mcp_servers.id')
                    ->where('agent_mcp_server.agent_id', $agentId);
            })
            ->where('mcp_servers.is_enabled', true)
            ->where('mcp_tools.permission', '!=', 'blocked')
            ->orderBy('mcp_servers.slug')
            ->orderBy('mcp_tools.name')
            ->get();

        return $tools->map(function (McpTool $tool): ToolEntry {
            $prefix = 'mcp__'.$tool->getAttribute('server_slug').'__';

            return new ToolEntry(
                kind: 'mcp',
                sourceId: $tool->id,
                name: $prefix.$tool->name,
                description: $tool->description,
                parameters: $tool->input_schema ?? [],
                embedding: null,
            );
        })->all();
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
                sourceId: $fn->id,
                name: $fn->internal_name,
                description: $fn->description,
                parameters: $parameters,
                embedding: null,
            );
        })->all();
    }

    private function cacheKey(int $agentId): string
    {
        return 'agent:tools:'.$agentId;
    }
}
