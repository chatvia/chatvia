<?php

namespace Domain\Chat\Services\Tooling;

use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentFunction;
use Domain\Agents\Models\McpTool;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Compiles the **pool** of tools an agent can call into a uniform
 * `ToolEntry` list. The pool is the union of:
 *   - MCP tools (server enabled, tool enabled, server attached to this
 *     agent via `agent_mcp_server`)
 *   - Webhook functions (enabled, attached to this agent via
 *     `agent_function`)
 *
 * `ToolSelector` then narrows this pool to what actually gets shipped
 * to the LLM on a given turn (semantic routing + sticky tools).
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
        $entries = [];

        foreach ($this->compileMcpTools($agentId) as $entry) {
            $entries[] = $entry;
        }
        foreach ($this->compileWebhookTools($agentId) as $entry) {
            $entries[] = $entry;
        }

        return $entries;
    }

    /**
     * MCP tools live in `mcp_tools` (one row per tool per server) and
     * carry an inline embedding. We join through `mcp_servers` to honor
     * server-level enable flags, require an `agent_mcp_server` pivot row
     * (enabled) for this agent, and anti-join `agent_mcp_tool` to drop
     * tools the agent has hard-disabled.
     *
     * @return array<int, ToolEntry>
     */
    private function compileMcpTools(int $agentId): array
    {
        $query = McpTool::query()
            ->select(['mcp_tools.*', 'mcp_servers.slug as server_slug'])
            ->join('mcp_servers', 'mcp_servers.id', '=', 'mcp_tools.mcp_server_id')
            ->join('agent_mcp_server', function ($join) use ($agentId): void {
                $join->on('agent_mcp_server.mcp_server_id', '=', 'mcp_servers.id')
                    ->where('agent_mcp_server.agent_id', $agentId);
            })
            ->where('mcp_servers.enabled', true)
            ->where('mcp_tools.enabled', true)
            ->orderBy('mcp_servers.slug')
            ->orderBy('mcp_tools.name');

        $startedAt = microtime(true);
        $tools = $query->get();
        $elapsedMs = (microtime(true) - $startedAt) * 1000;

        $withEmbedding = $tools->filter(static fn (McpTool $t): bool => $t->embedding !== null)->count();

        Log::debug('ToolRegistry: mcp_tools queried from DB', [
            'agent_id' => $agentId,
            'sql' => $query->toRawSql(),
            'row_count' => $tools->count(),
            'with_embedding' => $withEmbedding,
            'without_embedding' => $tools->count() - $withEmbedding,
            'elapsed_ms' => round($elapsedMs, 2),
            'tool_names' => $tools->pluck('name')->all(),
        ]);

        return $tools->map(function (McpTool $tool): ToolEntry {
            $prefix = 'mcp__'.$tool->getAttribute('server_slug').'__';

            return new ToolEntry(
                kind: 'mcp',
                sourceId: $tool->id,
                name: $prefix.$tool->name,
                description: $tool->description,
                parameters: $tool->input_schema ?? [],
                embedding: $tool->embedding?->toArray(),
            );
        })->all();
    }

    /**
     * Webhook functions: each function row IS one tool, so the same
     * `functions.enabled` flag covers both server-level and tool-level
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
            ->where('functions.enabled', true)
            ->orderBy('functions.name')
            ->get();

        return $functions->map(function (AgentFunction $fn): ToolEntry {
            $schema = $fn->parameters_schema;
            $parameters = $schema === null
                ? []
                : (is_object($schema) && method_exists($schema, 'toArray') ? $schema->toArray() : (array) $schema);

            // PHPStan can't see the `embedding` cast on AgentFunction
            // until the column lands in the schema-driven model docblock
            // (post-migrate), so narrow defensively here.
            $rawEmbedding = $fn->getAttribute('embedding');
            $embedding = is_array($rawEmbedding) ? array_values(array_map('floatval', $rawEmbedding)) : null;

            return new ToolEntry(
                kind: 'function',
                sourceId: $fn->id,
                name: $fn->name,
                description: $fn->description,
                parameters: $parameters,
                embedding: $embedding,
            );
        })->all();
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

    private function cacheKey(int $agentId): string
    {
        return 'agent:tools:'.$agentId;
    }
}
