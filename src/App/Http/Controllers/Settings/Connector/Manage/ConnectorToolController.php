<?php

namespace App\Http\Controllers\Settings\Connector\Manage;

use App\Http\Controllers\Controller;
use Domain\Integration\Connector\Data\Catalog\ConnectorToolsResourceData;
use Domain\Integration\Mcp\Actions\ListConnectorToolsAction;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Http\Request;

/**
 * List the tool catalog for an account-level MCP/connector.
 */
class ConnectorToolController extends Controller
{
    public function __invoke(Request $request, string $slug, ListConnectorToolsAction $list): ConnectorToolsResourceData
    {
        abort_unless(has_team_permission('account.connectors'), 404);

        return $list->execute(AgentMcpServer::forSlugScoped($slug, $request->user()));
    }
}
