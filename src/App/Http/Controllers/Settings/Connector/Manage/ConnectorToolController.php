<?php

namespace App\Http\Controllers\Settings\Connector\Manage;

use Domain\Integration\Connector\Data\Catalog\ConnectorToolsResourceData;
use Domain\Integration\Mcp\Actions\ListConnectorToolsAction;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Http\Request;

/**
 * List the tool catalog for an account-level MCP/connector.
 */
class ConnectorToolController
{
    public function __invoke(Request $request, string $slug, ListConnectorToolsAction $list): ConnectorToolsResourceData
    {
        return $list->execute(AgentMcpServer::forSlugScoped($slug, $request->user()));
    }
}
