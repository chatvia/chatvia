<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Http\Requests\Api\V1\Agent\AttachAgentMcpRequest;
use App\Http\Resources\Api\V1\Tools\McpServerData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Http\JsonResponse;

/**
 * Attach / detach MCP servers to a specific agent.
 */
#[Group(name: 'Connectors', weight: 50)]
class AgentMcpAttachmentController
{
    /**
     * List an agent's connectors
     *
     * @return array{data: list<McpServerData>}
     */
    public function index(Agent $agent): array
    {
        return ['data' => $agent->mcpServers()->get()
            ->map(fn (AgentMcpServer $s): McpServerData => McpServerData::fromModel($s))->all()];
    }

    /**
     * Attach connector to agent
     *
     * @return array{data: McpServerData}
     */
    public function store(AttachAgentMcpRequest $request, Agent $agent): array
    {
        $data = $request->toData($agent);
        $server = $this->resolveServer($data->agent, $data->mcp_id);

        $data->agent->attachConnector($server);

        return ['data' => McpServerData::fromModel($server->fresh())];
    }

    /**
     * Detach connector from agent
     */
    public function destroy(Agent $agent, string $mcp): JsonResponse
    {
        $agent->detachConnector($this->resolveServer($agent, $mcp));

        return response()->json(null, 204);
    }

    private function resolveServer(Agent $agent, string $externalId): AgentMcpServer
    {
        return AgentMcpServer::query()
            ->where('user_id', $agent->user_id)
            ->where('external_id', $externalId)
            ->firstOrFail();
    }
}
