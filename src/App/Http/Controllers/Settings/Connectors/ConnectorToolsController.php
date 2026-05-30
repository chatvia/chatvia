<?php

namespace App\Http\Controllers\Settings\Connectors;

use App\Auth\Models\User;
use App\Http\Controllers\Controller;
use Domain\Agents\Models\AgentMcpServer;
use Domain\Connectors\Actions\ListConnectorToolsAction;
use Domain\Connectors\Data\ConnectorToolsResourceData;
use Illuminate\Http\Request;

/**
 * List the tool catalog for an account-level MCP/connector.
 */
class ConnectorToolsController extends Controller
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
