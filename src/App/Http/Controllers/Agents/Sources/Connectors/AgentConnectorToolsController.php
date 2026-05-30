<?php

namespace App\Http\Controllers\Agents\Sources\Connectors;

use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentMcpServer;
use Domain\Connectors\Actions\ListConnectorToolsAction;
use Domain\Connectors\Data\ConnectorToolsResourceData;
use Illuminate\Http\Request;

class AgentConnectorToolsController
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
