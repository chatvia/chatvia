<?php

namespace App\Http\Controllers\Api\V1\Agent;

use App\Http\Requests\Api\V1\Agent\AttachAgentMcpRequest;
use App\Http\Requests\Api\V1\CursorPageRequest;
use App\Http\Resources\Api\V1\Tools\McpServerData;
use Dedoc\Scramble\Attributes\Group;
use Domain\Agent\Models\Agent;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Http\JsonResponse;
use Support\Pagination\CursorPage;

/**
 * Attach / detach MCP servers to a specific agent. `can.access.agent` turns a
 * server of another workspace into a 404.
 */
#[Group(name: 'Connectors', weight: 50)]
class AgentMcpAttachmentController
{
    /**
     * List an agent's connectors
     *
     * The connectors attached to the agent, newest first. The list is paged
     * with a cursor: to get the next page, send the `next_cursor` of the
     * response as `cursor`. On the last page `next_cursor` is null.
     *
     * @return array{data: list<McpServerData>, next_cursor: string|null}
     */
    public function index(CursorPageRequest $request, Agent $agent): array
    {
        $page = CursorPage::newestFirst($agent->mcpServers(), $request->perPage(), $request->cursor());

        return [
            'data' => array_map(fn (AgentMcpServer $s): McpServerData => McpServerData::fromModel($s), $page->items()),
            'next_cursor' => $page->nextCursor()?->encode(),
        ];
    }

    /**
     * Attach connector to agent
     */
    public function store(AttachAgentMcpRequest $request, Agent $agent): JsonResponse
    {
        $data = $request->toData($agent);

        $data->agent->attachConnector($data->server);

        return response()->json(['data' => McpServerData::fromModel($data->server)], 201);
    }

    /**
     * Detach connector from agent
     */
    public function destroy(Agent $agent, AgentMcpServer $mcp): JsonResponse
    {
        $agent->detachConnector($mcp);

        return response()->json(null, 204);
    }
}
