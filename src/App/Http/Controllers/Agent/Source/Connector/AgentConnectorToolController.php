<?php

namespace App\Http\Controllers\Agent\Source\Connector;

use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Data\Catalog\ConnectorToolsResourceData;
use Domain\Integration\Mcp\Actions\ListConnectorToolsAction;
use Domain\Integration\Mcp\Models\AgentMcpServer;
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
