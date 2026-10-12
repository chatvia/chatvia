<?php

namespace Domain\Integration\Connector\Actions\Account;

use Domain\Integration\Connector\Data\Custom\AddCustomConnectorData;
use Domain\Integration\Connector\Enums\ConnectorAuthStrategy;
use Domain\Integration\Mcp\Actions\Catalog\SyncMcpServerCatalogAction;
use Domain\Integration\Mcp\Data\ProvisionedMcpServerData;
use Domain\Integration\Mcp\Enums\McpAuthType;
use Domain\Integration\Mcp\Enums\McpServerCategory;
use Domain\Integration\Mcp\Enums\McpTransport;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Throwable;

/**
 * Adds a custom MCP server (token or no-auth) to the workspace and syncs its
 * tool catalog. OAuth custom servers go through
 * BeginCustomConnectorOAuthAction instead.
 *
 * The catalog sync is a remote call, so no transaction is held across it: a
 * server that cannot be synced is removed again and the error rethrown.
 */
class AddCustomConnectorAction
{
    public function __construct(
        private readonly SyncMcpServerCatalogAction $syncCatalog,
    ) {}

    public function execute(AddCustomConnectorData $data): ProvisionedMcpServerData
    {
        // An MCP server is a workspace resource: own it by the workspace, not
        // the acting teammate, whose deletion would orphan it.
        $workspace = $data->user->workspace();
        $usesToken = $data->auth_strategy === ConnectorAuthStrategy::Token;

        $server = AgentMcpServer::create([
            'workspace_id' => $workspace->id,
            'category' => McpServerCategory::Custom,
            'connector_slug' => null,
            'name' => $data->name,
            'slug' => AgentMcpServer::uniqueSlugFor($workspace, $data->name),
            'url' => $data->url,
            'transport' => McpTransport::StreamableHttp,
            'auth_type' => $usesToken ? McpAuthType::Bearer : McpAuthType::None,
            'auth_value' => $usesToken ? ($data->token ?? '') : null,
            'headers' => null,
            'timeout_ms' => AgentMcpServer::DEFAULT_TIMEOUT_MS,
            'is_enabled' => true,
        ]);

        try {
            $tools = $this->syncCatalog->execute($server);
        } catch (Throwable $e) {
            $server->delete();

            throw $e;
        }

        return new ProvisionedMcpServerData(
            server: $server->fresh(),
            tools_count: $tools->count(),
        );
    }
}
