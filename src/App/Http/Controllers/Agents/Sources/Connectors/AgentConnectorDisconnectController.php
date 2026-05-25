<?php

namespace App\Http\Controllers\Agents\Sources\Connectors;

use Domain\Agents\Models\Agent;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AgentConnectorDisconnectController
{
    public function __invoke(Request $request, Agent $agent, string $slug): JsonResponse
    {
        abort_if($request->user()->cannot('update', $agent), 403);

        $server = $agent->mcpServers()
            ->where('connector_slug', $slug)
            ->first();

        abort_unless($server !== null, 404);

        $server->delete();

        return response()->json(['ok' => true]);
    }
}
