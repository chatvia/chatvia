<?php

namespace App\Http\Controllers\Agents\Sources\Connectors;

use Domain\Agents\Data\Resources\AgentMcpServerData;
use Domain\Agents\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentConnectorEnableController
{
    public function __invoke(Request $request, Agent $agent, string $slug): JsonResponse
    {
        abort_if($request->user()->cannot('update', $agent), 403);

        $server = $agent->mcpServers()->where('slug', $slug)->first();
        abort_unless($server !== null, 404);

        $server->forceFill(['enabled' => ! $server->enabled])->save();

        return response()->json([
            'data' => AgentMcpServerData::fromModel($server->fresh()),
        ]);
    }
}
