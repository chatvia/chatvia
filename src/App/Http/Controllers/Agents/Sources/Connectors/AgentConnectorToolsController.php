<?php

namespace App\Http\Controllers\Agents\Sources\Connectors;

use Domain\Agents\Models\Agent;
use Domain\Chat\Services\Tooling\Mcp\McpCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Returns the cached MCP tool catalog (in OpenAI-shaped tool descriptors)
 * for a single connected server on this agent. The slug here is the
 * AgentMcpServer slug — which for presets is the connector slug, and for
 * custom servers is the slugified user-given name.
 */
class AgentConnectorToolsController
{
    public function __construct(
        private readonly McpCatalog $catalog,
    ) {}

    public function __invoke(Request $request, Agent $agent, string $slug): JsonResponse
    {
        abort_if($request->user()->cannot('view', $agent), 404);

        $server = $agent->mcpServers()->where('slug', $slug)->first();
        abort_unless($server, 404);

        $tools = $this->catalog->for($server);

        // McpCatalog returns OpenAI shape; strip the wrapper to send a
        // flatter list to the UI.
        $flattened = array_map(fn (array $entry) => [
            'name' => (string) ($entry['function']['name'] ?? ''),
            'short_name' => $this->stripPrefix((string) ($entry['function']['name'] ?? ''), $server->toolPrefix()),
            'description' => (string) ($entry['function']['description'] ?? ''),
            'parameters' => $entry['function']['parameters'] ?? new \stdClass,
        ], $tools);

        return response()->json([
            'tools' => $flattened,
            'cached_at' => $server->catalog_cached_at,
        ]);
    }

    private function stripPrefix(string $name, string $prefix): string
    {
        return str_starts_with($name, $prefix) ? substr($name, strlen($prefix)) : $name;
    }
}
