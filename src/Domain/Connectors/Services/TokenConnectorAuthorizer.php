<?php

namespace Domain\Connectors\Services;

use App\Auth\Models\User;
use Domain\Agents\Models\AgentMcpServer;
use Domain\Chat\Services\Tooling\Mcp\McpCatalog;
use Domain\Chat\Services\Tooling\Mcp\McpException;
use Domain\Connectors\Data\ConnectorData;
use Illuminate\Support\Facades\DB;
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
     * @return array{server: AgentMcpServer, tools_count: int}
     *
     * @throws McpException when the token / endpoint fails validation
     */
    public function connect(User $user, ConnectorData $connector, ?string $token = null): array
    {
        return DB::transaction(function () use ($user, $connector, $token) {
            $existing = AgentMcpServer::query()
                ->where('user_id', $user->id)
                ->where('connector_slug', $connector->slug)
                ->first();

            $noAuth = $connector->authStrategy === 'none';

            $attrs = [
                'user_id' => $user->id,
                'connector_slug' => $connector->slug,
                'name' => $connector->name,
                'slug' => AgentMcpServer::makeSlug($connector->slug),
                'url' => $connector->mcpUrl,
                'transport' => 'streamable_http',
                'auth_type' => $noAuth ? 'none' : ($connector->authType ?? 'bearer'),
                'auth_value' => $noAuth ? null : $token,
                'headers' => null,
                'timeout_ms' => 30000,
                'is_enabled' => true,
                'last_error' => null,
            ];

            if ($existing) {
                $existing->fill($attrs)->save();
                $server = $existing;
            } else {
                $server = AgentMcpServer::create($attrs);
            }

            try {
                $tools = $this->catalog->refresh($server);
            } catch (Throwable $e) {
                throw new McpException($this->humanize($e->getMessage()), previous: $e);
            }

            return [
                'server' => $server->fresh(),
                'tools_count' => count($tools),
            ];
        });
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
