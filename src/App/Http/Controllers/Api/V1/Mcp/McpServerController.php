<?php

namespace App\Http\Controllers\Api\V1\Mcp;

use App\Http\Requests\Api\V1\CursorPageRequest;
use App\Http\Resources\Api\V1\Tools\McpServerData;
use App\Identity\Workspace\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Http\JsonResponse;
use Support\Pagination\CursorPage;

/**
 * Your custom MCP servers. Create and edit them in the dashboard (Connectors);
 * here you can list, inspect, and delete them, and attach them to agents via
 * `POST /api/v1/agents/{id}/mcps`. Built-in catalog connectors are never
 * exposed here — only your custom entries. Auth secrets are never returned.
 *
 * `workspace.scope` turns another workspace's server into a 404; a catalog
 * connector (the workspace's own or a built-in one) is a 404 here as well.
 */
#[Group(name: 'Connectors', weight: 50)]
class McpServerController
{
    /**
     * List connectors
     *
     * Your custom MCP servers, newest first. The list is paged with a cursor:
     * to get the next page, send the `next_cursor` of the response as
     * `cursor`. On the last page `next_cursor` is null.
     *
     * @return array{data: list<McpServerData>, next_cursor: string|null}
     */
    public function index(CursorPageRequest $request): array
    {
        /** @var User $user */
        $user = $request->user();

        $page = CursorPage::newestFirst($user->workspace()->mcpServers()->custom(), $request->perPage(), $request->cursor());

        return [
            'data' => array_map(fn (AgentMcpServer $server): McpServerData => McpServerData::fromModel($server), $page->items()),
            'next_cursor' => $page->nextCursor()?->encode(),
        ];
    }

    /**
     * Get connector
     *
     * @return array{data: McpServerData}
     */
    public function show(AgentMcpServer $mcp): array
    {
        abort_unless($mcp->isCustom(), 404);

        return ['data' => McpServerData::fromModel($mcp)];
    }

    /**
     * Delete connector
     *
     * Also removes it from every agent it's attached to.
     */
    public function destroy(AgentMcpServer $mcp): JsonResponse
    {
        abort_unless($mcp->isCustom(), 404);

        $mcp->delete();

        return response()->json(null, 204);
    }
}
