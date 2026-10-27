<?php

namespace App\Http\Controllers\Settings\Connector\Manage;

use Domain\Integration\Connector\Data\Catalog\ConnectorToolsResourceData;
use Domain\Integration\Connector\Queries\ConnectorToolsQuery;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Http\Request;

/**
 * List the tool catalog for an account-level MCP/connector.
 */
class ConnectorToolController
{
    public function __invoke(Request $request, string $slug, ConnectorToolsQuery $list): ConnectorToolsResourceData
    {
        return $list->get(AgentMcpServer::forSlugScoped($slug, $request->user()));
    }
}
