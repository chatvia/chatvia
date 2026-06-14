<?php

namespace Domain\Integration\Connector\Services;

use App\Identity\Workspace\Models\User;
use Domain\Integration\Connector\Data\Catalog\ConnectorData;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Domain\Integration\Mcp\Services\McpCatalog;
use Domain\Integration\Mcp\Services\McpException;
use Throwable;

/**
 * Connects a token-based catalog connector at the account level. The
 * resulting AgentMcpServer row is owned by the user and attached to
 * agents via the `agent_mcp_server` pivot.
 *
 * Enforces "one connector_slug per customer total" — upserts on
 * (user_id, connector_slug).
 */
class TokenConnectorAuthorizer
{
    public function __construct(
        private readonly McpCatalog $catalog,
    ) {}

    /**
     * @param  string|null  $url  User-supplied MCP server URL, for presets that
     *                            carry no fixed endpoint (`requiresUrl`). Falls
     *                            back to the connector's configured `mcpUrl`.
     * @return array{server: AgentMcpServer, tools_count: int}
     *
     * @throws McpException when the token / endpoint fails validation
     */
    public function connect(User $user, ConnectorData $connector, ?string $token = null, ?string $url = null): array
    {
        // A connector is a workspace resource ("one connector_slug per customer")
        // — own it by the workspace, not the acting employee, whose deletion
        // would SET NULL user_id and orphan/globalise the server.
        $owner = $user->workspace();

        $existing = AgentMcpServer::query()
            ->where('user_id', $owner->id)
            ->where('connector_slug', $connector->slug)
            ->first();

        $noAuth = $connector->authStrategy === 'none';

        $attrs = [
            'user_id' => $owner->id,
            'category' => $connector->serverCategory(),
            'connector_slug' => $connector->slug,
            'name' => $connector->name,
            'slug' => AgentMcpServer::makeSlug($connector->slug),
            'url' => $url ?: $connector->mcpUrl,
            'transport' => 'streamable_http',
            'auth_type' => $noAuth ? 'none' : ($connector->authType ?? 'bearer'),
            'auth_value' => $noAuth ? null : $token,
            'headers' => null,
            'timeout_ms' => 300000,
            'is_enabled' => true,
            'last_error' => null,
        ];

        // Commit before the refresh (remote HTTP) — compensate on failure, no transaction.
        if ($existing) {
            $original = $existing->getOriginal();
            $existing->fill($attrs)->save();
            $server = $existing;
        } else {
            $server = AgentMcpServer::create($attrs);
        }

        try {
            $tools = $this->catalog->refresh($server);
        } catch (Throwable $e) {
            // A failed token swap must leave the previous credentials in place.
            if ($existing) {
                $existing->fill(array_intersect_key($original, $attrs))->save();
            } else {
                $server->delete();
            }

            throw new McpException($this->humanize($e->getMessage()), previous: $e);
        }

        return [
            'server' => $server->fresh(),
            'tools_count' => count($tools),
        ];
    }

    /**
     * Translate raw transport errors into something a user can act on.
     */
    private function humanize(string $message): string
    {
        $lower = mb_strtolower($message);

        if (str_contains($lower, '401') || str_contains($lower, 'unauthorized')) {
            return 'The server rejected the token (401 Unauthorized). Double-check the value and try again.';
        }
        if (str_contains($lower, '403')) {
            return 'The token is valid but does not have permission to call this MCP server (403).';
        }
        if (str_contains($lower, '404')) {
            return 'The connector endpoint returned 404. The service may have moved — please report this.';
        }
        if (str_contains($lower, 'could not reach') || str_contains($lower, 'timed out') || str_contains($lower, 'curl error')) {
            return 'Could not reach the MCP server. Check your network and try again.';
        }

        return $message;
    }
}
