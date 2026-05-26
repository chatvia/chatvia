<?php

namespace App\Http\Controllers\Api\V1\Mcps;

use App\Auth\Models\User;
use Domain\Agents\Models\AgentMcpServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * @tags MCP Servers
 *
 * Account-level **custom** MCP servers. Define a server once (Linear, your
 * internal tools, etc.) and attach it to any number of your agents via
 * `POST /api/v1/agents/{id}/mcps`. Built-in catalog connectors (Slack,
 * Calendar, Fireflies, …) live in the same table but are never exposed
 * here — only entries with `connector_slug = NULL`.
 *
 * Auth secrets (`auth_value`, `headers`) are encrypted at rest and never
 * returned in responses; they are write-only.
 *
 * Master token required (ability `agents:manage`).
 */
class McpServerController
{
    /**
     * List MCP servers
     */
    public function index(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $servers = $user->mcpServers()
            ->whereNull('connector_slug')
            ->latest()
            ->get();

        return response()->json([
            'data' => $servers->map(fn (AgentMcpServer $m): array => $this->transform($m))->all(),
        ]);
    }

    /**
     * Create MCP server
     *
     * Adds a new account-level custom MCP. To make it available to a specific
     * agent, call `POST /api/v1/agents/{id}/mcps` with the returned `id`.
     */
    public function store(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:64'],
            'url' => ['required', 'url', 'max:2048'],
            'transport' => ['sometimes', 'in:streamable_http,http_sse'],
            'auth_type' => ['sometimes', 'in:none,bearer,header'],
            'auth_value' => ['sometimes', 'nullable', 'string', 'max:8192'],
            'headers' => ['sometimes', 'nullable', 'array'],
            'headers.*' => ['string'],
            'timeout_ms' => ['sometimes', 'integer', 'min:1000', 'max:60000'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        $server = $user->mcpServers()->create([
            'name' => $validated['name'],
            'url' => $validated['url'],
            'transport' => $validated['transport'] ?? 'streamable_http',
            'auth_type' => $validated['auth_type'] ?? 'none',
            'auth_value' => $validated['auth_value'] ?? null,
            'headers' => $validated['headers'] ?? null,
            'timeout_ms' => $validated['timeout_ms'] ?? 30000,
            'enabled' => $validated['enabled'] ?? true,
            'connector_slug' => null,
        ]);

        return response()->json(['mcp' => $this->transform($server)], 201);
    }

    /**
     * Get MCP server
     */
    public function show(Request $request, string $mcp): JsonResponse
    {
        return response()->json(['mcp' => $this->transform($this->findCustom($request, $mcp))]);
    }

    /**
     * Update MCP server
     *
     * Patches the shared definition — any change instantly takes effect for
     * every agent it's attached to (catalog cache busts via observer).
     */
    public function update(Request $request, string $mcp): JsonResponse
    {
        $server = $this->findCustom($request, $mcp);

        $validated = $request->validate([
            'name' => ['sometimes', 'string', 'max:64'],
            'url' => ['sometimes', 'url', 'max:2048'],
            'transport' => ['sometimes', 'in:streamable_http,http_sse'],
            'auth_type' => ['sometimes', 'in:none,bearer,header'],
            'auth_value' => ['sometimes', 'nullable', 'string', 'max:8192'],
            'headers' => ['sometimes', 'nullable', 'array'],
            'timeout_ms' => ['sometimes', 'integer', 'min:1000', 'max:60000'],
            'enabled' => ['sometimes', 'boolean'],
        ]);

        $server->update($validated);

        return response()->json(['mcp' => $this->transform($server->refresh())]);
    }

    /**
     * Delete MCP server
     *
     * Also detaches it from every agent (CASCADE on the pivot).
     */
    public function destroy(Request $request, string $mcp): JsonResponse
    {
        $this->findCustom($request, $mcp)->delete();

        return response()->json(null, 204);
    }

    private function findCustom(Request $request, string $externalId): AgentMcpServer
    {
        /** @var User $user */
        $user = $request->user();

        /** @var AgentMcpServer $server */
        $server = $user->mcpServers()
            ->whereNull('connector_slug')
            ->where('external_id', $externalId)
            ->firstOrFail();

        return $server;
    }

    private function transform(AgentMcpServer $server): array
    {
        return [
            'id' => $server->external_id,
            'name' => $server->name,
            'slug' => $server->slug,
            'url' => $server->url,
            'transport' => $server->transport,
            'auth_type' => $server->auth_type,
            'has_auth_value' => $server->auth_value !== null,
            'has_headers' => $server->headers !== null && $server->headers !== [],
            'timeout_ms' => $server->timeout_ms,
            'enabled' => $server->enabled,
            'catalog_tools_count' => $server->catalog_tools_count,
            'last_invoked_at' => $server->last_invoked_at?->toIso8601String(),
            'invocation_count' => $server->invocation_count,
            'created_at' => $server->created_at?->toIso8601String(),
        ];
    }
}
