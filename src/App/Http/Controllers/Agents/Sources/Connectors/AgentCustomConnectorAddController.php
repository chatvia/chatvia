<?php

namespace App\Http\Controllers\Agents\Sources\Connectors;

use App\Http\Requests\Agents\Connectors\AgentCustomConnectorRequest;
use Domain\Agents\Data\Resources\AgentMcpServerData;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentMcpServer;
use Domain\Chat\Services\Tooling\Mcp\McpCatalog;
use Domain\Chat\Services\Tooling\Mcp\McpException;
use Domain\Connectors\Services\OAuth\McpOAuthException;
use Domain\Connectors\Services\OAuth\McpOAuthFlow;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Adds a user-supplied MCP server (no catalog entry) to an agent.
 *
 *   - `auth_strategy: 'none'`  → create the row and probe tools immediately.
 *   - `auth_strategy: 'token'` → same, but with a bearer the user pasted.
 *   - `auth_strategy: 'oauth'` → kick the OAuth flow; the row is created on
 *     callback. We return `{ redirect_url }` so the frontend can navigate.
 */
class AgentCustomConnectorAddController
{
    public function __construct(
        private readonly McpCatalog $catalog,
        private readonly McpOAuthFlow $oauthFlow,
    ) {}

    public function __invoke(AgentCustomConnectorRequest $request, Agent $agent): JsonResponse
    {
        abort_if($request->user()->cannot('update', $agent), 403);

        $data = $request->validated();
        $strategy = (string) $data['auth_strategy'];

        if ($strategy === 'oauth') {
            $slug = $this->uniqueCustomSlug($agent, (string) $data['name']);

            try {
                $url = $this->oauthFlow->beginCustom(
                    agent: $agent,
                    name: (string) $data['name'],
                    slug: $slug,
                    mcpUrl: (string) $data['url'],
                );
            } catch (McpOAuthException $e) {
                return response()->json(['message' => $e->getMessage()], 422);
            }

            return response()->json(['redirect_url' => $url]);
        }

        try {
            $result = DB::transaction(function () use ($agent, $data, $strategy) {
                $server = AgentMcpServer::create([
                    'agent_id' => $agent->id,
                    'connector_slug' => null,
                    'name' => (string) $data['name'],
                    'slug' => $this->uniqueCustomSlug($agent, (string) $data['name']),
                    'url' => (string) $data['url'],
                    'transport' => 'streamable_http',
                    'auth_type' => $strategy === 'token' ? 'bearer' : 'none',
                    'auth_value' => $strategy === 'token' ? (string) $data['token'] : null,
                    'headers' => null,
                    'timeout_ms' => 30000,
                    'enabled' => true,
                ]);

                $tools = $this->catalog->refresh($server);

                return ['server' => $server->fresh(), 'tools_count' => count($tools)];
            });
        } catch (Throwable $e) {
            $message = $e instanceof McpException ? $e->getMessage() : 'Could not connect to the MCP server: '.$e->getMessage();

            return response()->json(['message' => $message], 422);
        }

        return response()->json([
            'data' => AgentMcpServerData::fromModel($result['server']),
            'tools_count' => $result['tools_count'],
        ], 201);
    }

    /**
     * Build a slug from the user's name that doesn't collide with another
     * AgentMcpServer on the same agent.
     */
    private function uniqueCustomSlug(Agent $agent, string $name): string
    {
        $base = AgentMcpServer::makeSlug($name);
        $slug = $base;
        $i = 2;

        while ($agent->mcpServers()->where('slug', $slug)->exists()) {
            $slug = substr($base, 0, 38).'_'.$i;
            $i++;
        }

        return $slug;
    }
}
