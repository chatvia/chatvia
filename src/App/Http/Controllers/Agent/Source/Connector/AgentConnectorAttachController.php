<?php

namespace App\Http\Controllers\Agent\Source\Connector;

use Domain\Agent\Models\Agent;
use Domain\Integration\Connector\Actions\Agent\AttachGenericConnectorToAgentAction;
use Domain\Integration\Connector\Data\Agent\AgentConnectorAttachResultData;
use Domain\Integration\Mcp\Data\Resources\AgentMcpServerData;
use Domain\Integration\Mcp\Models\AgentMcpServer;
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
