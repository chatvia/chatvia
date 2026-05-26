<?php

namespace App\Http\Controllers\Agents\Sources\Connectors;

use Domain\Agents\Data\Resources\AgentMcpServerData;
use Domain\Agents\Models\Agent;
use Domain\Agents\Models\AgentMcpServer;
use Domain\Connectors\Actions\AttachGenericConnectorToAgentAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentConnectorAttachController
{
    public function __invoke(
        Request $request,
        Agent $agent,
        string $slug,
        AttachGenericConnectorToAgentAction $attach,
    ): JsonResponse {
        abort_if($request->user()->cannot('update', $agent), 403);

        $server = AgentMcpServer::query()
            ->where('user_id', $agent->user_id)
            ->where('slug', $slug)
            ->first();

        abort_unless($server !== null, 404, 'Connector not found in your account.');

        $fresh = $attach->execute($agent, $server);

        return response()->json([
            'data' => [
                ...AgentMcpServerData::fromModel($fresh)->toArray(),
                'is_attached' => true,
            ],
        ], 201);
    }
}
