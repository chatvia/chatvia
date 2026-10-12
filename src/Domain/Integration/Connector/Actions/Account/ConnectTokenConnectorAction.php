<?php

namespace Domain\Integration\Connector\Actions\Account;

use Domain\Integration\Connector\Data\Account\ConnectTokenConnectorData;
use Domain\Integration\Connector\Enums\ConnectorAuthStrategy;
use Domain\Integration\Mcp\Actions\Catalog\SyncMcpServerCatalogAction;
use Domain\Integration\Mcp\Data\ProvisionedMcpServerData;
use Domain\Integration\Mcp\Enums\McpTransport;
use Domain\Integration\Mcp\Exceptions\McpException;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Throwable;

/**
 * Connects a token or no-auth catalog connector for a workspace and syncs its
 * tool catalog. A workspace has at most one server per connector, so a repeat
 * connect (a new token, say) updates the existing row.
 *
 * The catalog sync is a remote call, so no transaction is held across it.
 * Instead a failed sync is undone by hand: a token swap restores the previous
 * credentials, a first connect removes the row. Throws McpException with a
 * message the workspace can act on.
 */
class ConnectTokenConnectorAction
{
    public function __construct(
        private readonly SyncMcpServerCatalogAction $syncCatalog,
    ) {}

    public function execute(ConnectTokenConnectorData $data): ProvisionedMcpServerData
    {
        // A connector is a workspace resource: own it by the workspace, not the
        // acting teammate, whose deletion would orphan the server.
        $workspace = $data->user->workspace();
        $connector = $data->connector;
        $noAuth = $connector->authStrategy === ConnectorAuthStrategy::None;

        $attributes = [
            'workspace_id' => $workspace->id,
            'category' => $connector->serverCategory(),
            'connector_slug' => $connector->slug,
            'name' => $connector->name,
            'slug' => AgentMcpServer::makeSlug($connector->slug),
            'url' => $data->url ?: $connector->mcpUrl,
            'transport' => McpTransport::StreamableHttp,
            'auth_type' => $connector->serverAuthType(),
            'auth_value' => $noAuth ? null : $data->token,
            'headers' => null,
            'timeout_ms' => AgentMcpServer::DEFAULT_TIMEOUT_MS,
            'is_enabled' => true,
            'last_error' => null,
        ];

        $existing = $workspace->mcpServers()->where('connector_slug', $connector->slug)->first();
        $previous = $existing?->getOriginal();

        $server = $existing ?? new AgentMcpServer;
        $server->fill($attributes)->save();

        try {
            $tools = $this->syncCatalog->execute($server);
        } catch (Throwable $e) {
            $this->undo($server, $previous, $attributes);

            throw new McpException($this->humanize($e->getMessage()), $e->getCode(), previous: $e);
        }

        return new ProvisionedMcpServerData(
            server: $server->fresh(),
            tools_count: $tools->count(),
        );
    }

    /**
     * @param  array<string, mixed>|null  $previous  The row as it was before this connect; null for a first connect.
     * @param  array<string, mixed>  $attributes
     */
    private function undo(AgentMcpServer $server, ?array $previous, array $attributes): void
    {
        if ($previous === null) {
            $server->delete();

            return;
        }

        $server->fill(array_intersect_key($previous, $attributes))->save();
    }

    /**
     * Translate raw transport errors into something a user can act on.
     */
    private function humanize(string $message): string
    {
        $lower = mb_strtolower($message);

        return match (true) {
            str_contains($lower, '401') || str_contains($lower, 'unauthorized') => __('The server rejected the token (401 Unauthorized). Double-check the value and try again.'),
            str_contains($lower, '403') => __('The token is valid but does not have permission to call this MCP server (403).'),
            str_contains($lower, '404') => __('The connector endpoint returned 404. The service may have moved — please report this.'),
            str_contains($lower, 'could not reach'),
            str_contains($lower, 'timed out'),
            str_contains($lower, 'curl error') => __('Could not reach the MCP server. Check your network and try again.'),
            default => $message,
        };
    }
}
