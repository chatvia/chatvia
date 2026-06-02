<?php

namespace App\Http\Controllers\Agent\Source\Connector;

use Domain\Agent\Models\Agent;
use Domain\Mcp\Models\AgentMcpServer;
use Domain\Mcp\Actions\ListConnectorToolsAction;
use Domain\Connector\Data\Catalog\ConnectorToolsResourceData;
use Illuminate\Http\Request;

class AgentConnectorToolController
{
    public function __invoke(
        Request $request,
        Agent $agent,
        string $slug,
        ListConnectorToolsAction $list,
    ): ConnectorToolsResourceData {
        abort_if($request->user()->cannot('view', $agent), 404);

        $server = AgentMcpServer::query()
            ->attachedToAgent($agent)
            ->where('slug', $slug)
            ->first();

        abort_unless($server !== null, 404);

        return $list->execute($server);
    }
}
