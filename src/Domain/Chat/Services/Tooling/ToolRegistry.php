<?php

namespace Domain\Chat\Services\Tooling;

use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentFunction;
use Domain\Agents\Models\AgentMcpServer;
use Domain\Chat\Services\Tooling\Mcp\McpCatalog;
use Illuminate\Support\Facades\Cache;

class ToolRegistry
{
    private const CACHE_TTL = 3600;

    public function __construct(
        private readonly McpCatalog $mcpCatalog,
    ) {}

    /**
     * Return the OpenAI-compatible `tools` payload for the given agent, or an
     * empty array if the agent has no enabled functions and no MCP servers.
     *
     * Hot-path optimization: the `agents.functions_count` and
     * `agents.mcp_servers_count` counter caches are consulted first. If both
     * are 0, we skip the DB query entirely. Agents with no tools therefore
     * pay essentially zero cost per chat turn.
     *
     * @return array<int, array<string, mixed>>
     */
    public function for(Agent $agent): array
    {
        $hasFunctions = ($agent->functions_count ?? 0) > 0;
        $hasMcp = ($agent->mcp_servers_count ?? 0) > 0;

        if (! $hasFunctions && ! $hasMcp) {
            return [];
        }

        $webhookTools = $hasFunctions
            ? Cache::remember(
                $this->cacheKey($agent->id),
                self::CACHE_TTL,
                fn (): array => $this->compileWebhookTools($agent->id),
            )
            : [];

        $mcpTools = $hasMcp ? $this->compileMcpTools($agent->id) : [];

        return array_merge($webhookTools, $mcpTools);
    }

    public function forget(int $agentId): void
    {
        Cache::forget($this->cacheKey($agentId));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function compileWebhookTools(int $agentId): array
    {
        return AgentFunction::query()
            ->where('agent_id', $agentId)
            ->where('enabled', true)
            ->orderBy('name')
            ->get(['name', 'description', 'parameters_schema'])
            ->map(fn (AgentFunction $fn): array => [
                'type' => 'function',
                'function' => [
                    'name' => $fn->name,
                    'description' => $fn->description,
                    'parameters' => $this->schemaForLlm($fn->parameters_schema),
                ],
            ])
            ->all();
    }

    /**
     * MCP tools are cached per-server inside McpCatalog with its own TTL —
     * we just merge what each enabled server contributes.
     *
     * @return array<int, array<string, mixed>>
     */
    private function compileMcpTools(int $agentId): array
    {
        $servers = AgentMcpServer::query()
            ->where('agent_id', $agentId)
            ->where('enabled', true)
            ->orderBy('slug')
            ->get();

        $tools = [];
        foreach ($servers as $server) {
            foreach ($this->mcpCatalog->for($server) as $tool) {
                $tools[] = $tool;
            }
        }

        return $tools;
    }

    /**
     * @return array<string, mixed>
     */
    private function schemaForLlm(mixed $schema): array
    {
        if ($schema === null) {
            return ['type' => 'object', 'properties' => (object) [], 'required' => []];
        }

        if (is_object($schema) && method_exists($schema, 'toArray')) {
            return $schema->toArray();
        }

        return (array) $schema;
    }

    private function cacheKey(int $agentId): string
    {
        return 'agent:tools:'.$agentId;
    }
}
