<?php

namespace App\Http\Controllers\Settings\Connector\Manage;

use App\Http\Controllers\Controller;
use App\Identity\Workspace\Models\User;
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

        return $list->execute($this->find($request, $slug));
    }

    private function find(Request $request, string $slug): AgentMcpServer
    {
        /** @var User $user */
        $user = $request->user();
        $server = $user->mcpServers()->where('slug', $slug)->first();

        abort_unless($server !== null, 404);

        return $server;
    }
}
