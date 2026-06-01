<?php

namespace App\Http\Controllers\Agents\Sources\Connectors;

use Domain\Agents\Data\Resources\Mcp\AgentMcpServerData;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentMcpServer;
use Domain\Connectors\Actions\Agent\AttachGenericConnectorToAgentAction;
use Domain\Connectors\Data\AgentConnectorAttachResultData;
use Illuminate\Http\Request;

class AgentConnectorAttachController
{
    public function __invoke(
        Request $request,
        Agent $agent,
        string $slug,
        AttachGenericConnectorToAgentAction $attach,
    ): AgentConnectorAttachResultData {
        abort_if($request->user()->cannot('update', $agent), 403);

        $server = AgentMcpServer::query()
            ->ownedBySameAccountAs($agent)
            ->where('slug', $slug)
            ->first();

        abort_unless($server !== null, 404, 'Connector not found in your account.');

        $fresh = $attach->execute($agent, $server);

        return new AgentConnectorAttachResultData(
            data: AgentMcpServerData::fromModel($fresh),
        );
    }
}
