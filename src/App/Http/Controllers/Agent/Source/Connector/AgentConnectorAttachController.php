<?php

namespace App\Http\Controllers\Agent\Source\Connector;

use Domain\Mcp\Data\Resources\AgentMcpServerData;
use Domain\Agent\Models\Agent;
use Domain\Mcp\Models\AgentMcpServer;
use Domain\Connector\Actions\Agent\AttachGenericConnectorToAgentAction;
use Domain\Connector\Data\Agent\AgentConnectorAttachResultData;
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
