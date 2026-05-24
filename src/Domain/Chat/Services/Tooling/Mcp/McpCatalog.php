<?php

namespace Domain\Chat\Services\Tooling\Mcp;

use Domain\Agents\Models\AgentMcpServer;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Per-server tool catalog: fetch via McpClient, cache, return in the OpenAI
 * tools[] shape with the `mcp__{slug}__` prefix applied so a single flat
 * tool namespace works for the LLM.
 */
class McpCatalog
{
    public function __construct(
        private readonly McpClient $client,
    ) {}

    private const CACHE_TTL = 21600; // 6 hours

    /**
     * Return the LLM-shaped tool list for a server. Empty array if disabled
     * or fetch failed.
     *
     * @return array<int, array<string, mixed>>
     */
    public function for(AgentMcpServer $server, bool $forceRefresh = false): array
    {
        if (! $server->enabled) {
            return [];
        }

        $cacheKey = $this->cacheKey($server->id);

        if ($forceRefresh) {
            Cache::forget($cacheKey);
        }

        return Cache::remember($cacheKey, self::CACHE_TTL, function () use ($server): array {
            return $this->fetchAndCompile($server);
        });
    }

    /**
     * Force a refresh AND return what we just fetched. Used by the connect
     * flow to fail fast on a bad URL/token, and by the manual "Refresh
     * tools" button.
     *
     * @return array<int, array<string, mixed>>
     */
    public function refresh(AgentMcpServer $server): array
    {
        Cache::forget($this->cacheKey($server->id));
        $tools = $this->fetchAndCompile($server, throwOnError: true);
        Cache::put($this->cacheKey($server->id), $tools, self::CACHE_TTL);

        return $tools;
    }

    public function forget(AgentMcpServer $server): void
    {
        Cache::forget($this->cacheKey($server->id));
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetchAndCompile(AgentMcpServer $server, bool $throwOnError = false): array
    {
        try {
            $rawTools = $this->client->fetchTools($server);
        } catch (Throwable $e) {
            Log::warning('MCP catalog fetch failed', [
                'server_id' => $server->id,
                'slug' => $server->slug,
                'error' => $e->getMessage(),
            ]);

            $server->forceFill([
                'last_error' => $e->getMessage(),
            ])->save();

            if ($throwOnError) {
                throw $e;
            }

            return [];
        }

        $prefix = $server->toolPrefix();
        $compiled = [];

        foreach ($rawTools as $tool) {
            if (! is_array($tool) || ! isset($tool['name']) || ! is_string($tool['name'])) {
                continue;
            }

            $prefixedName = $prefix.$tool['name'];

            // OpenAI's tool name regex permits a-zA-Z0-9_-. Slugs are
            // already constrained and MCP tool names are typically safe,
            // but we re-validate defensively.
            if (! preg_match('/^[a-zA-Z][a-zA-Z0-9_-]{0,63}$/', $prefixedName)) {
                continue;
            }

            $compiled[] = [
                'type' => 'function',
                'function' => [
                    'name' => $prefixedName,
                    'description' => (string) ($tool['description'] ?? ''),
                    'parameters' => $this->schemaForLlm($tool['inputSchema'] ?? null),
                ],
            ];
        }

        $server->forceFill([
            'catalog_cached_at' => now(),
            'catalog_tools_count' => count($compiled),
            'last_error' => null,
        ])->save();

        return $compiled;
    }

    /**
     * @return array<string, mixed>
     */
    private function schemaForLlm(mixed $schema): array
    {
        if (is_array($schema)) {
            return $schema;
        }

        return ['type' => 'object', 'properties' => (object) []];
    }

    private function cacheKey(int $serverId): string
    {
        return 'agent:mcp:'.$serverId.':catalog';
    }
}
