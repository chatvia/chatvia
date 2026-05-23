<?php

namespace App\Services\Chat\Tooling;

use App\Models\Agent;
use App\Models\AgentFunction;
use Illuminate\Support\Facades\Cache;

class ToolRegistry
{
    private const CACHE_TTL = 3600;

    /**
     * Return the OpenAI-compatible `tools` payload for the given agent, or an
     * empty array if the agent has no enabled functions.
     *
     * Hot-path optimization: the `agents.functions_count` counter cache is
     * consulted first. If it is 0, we skip the DB query entirely. Agents
     * with no functions therefore pay essentially zero cost per chat turn.
     *
     * When the count is non-zero, the compiled payload is cached per agent
     * and busted on AgentFunction create/update/delete via the observer.
     *
     * @return array<int, array<string, mixed>>
     */
    public function for(Agent $agent): array
    {
        if (($agent->functions_count ?? 0) === 0) {
            return [];
        }

        return Cache::remember(
            $this->cacheKey($agent->id),
            self::CACHE_TTL,
            fn (): array => $this->compile($agent->id),
        );
    }

    public function forget(int $agentId): void
    {
        Cache::forget($this->cacheKey($agentId));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function compile(int $agentId): array
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
