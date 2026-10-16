<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1\Mcp;

use App\Http\Requests\Api\V1\CursorPageRequest;
use App\Http\Requests\Api\V1\Mcp\StoreApiMcpServerRequest;
use App\Http\Resources\Api\V1\Tool\McpServerData;
use App\Identity\Workspace\Models\User;
use Dedoc\Scramble\Attributes\Group;
use Domain\Integration\Connector\Actions\Account\AddCustomConnectorAction;
use Domain\Integration\Mcp\Exceptions\McpException;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Support\Pagination\CursorPage;
use Throwable;

/**
 * The MCP servers connected to your account: your custom entries (`kind:
 * custom`) and the catalog connectors you connected in the dashboard
 * (`kind: catalog`). Both can be attached to agents via
 * `POST /api/v1/agents/{id}/mcps`; custom servers can also be created and
 * deleted here, while catalog connectors are managed in the dashboard (their
 * OAuth flows need a browser). Auth secrets are never returned.
 *
 * `workspace.scope` turns another workspace's server into a 404.
 */
#[Group(name: 'Connectors', weight: 50)]
class McpServerController
{
    /**
     * List connectors
     *
     * The MCP servers connected to your account, newest first. The list is
     * paged with a cursor: to get the next page, send the `next_cursor` of
     * the response as `cursor`. On the last page `next_cursor` is null.
     *
     * @return array{data: list<McpServerData>, next_cursor: string|null}
     */
    public function index(CursorPageRequest $request): array
    {
        /** @var User $user */
        $user = $request->user();

        $page = CursorPage::newestFirst($user->workspace()->mcpServers(), $request->perPage(), $request->cursor());

        return [
            'data' => array_map(McpServerData::fromModel(...), $page->items()),
            'next_cursor' => $page->nextCursor()?->encode(),
        ];
    }

    /**
     * Add connector
     *
     * Adds a custom MCP server by URL (bearer token or no auth; OAuth servers
     * are connected in the dashboard) and syncs its tool catalog. A server
     * that cannot be reached or synced is not kept (422).
     */
    public function store(StoreApiMcpServerRequest $request, AddCustomConnectorAction $add): JsonResponse
    {
        try {
            $result = $add->execute($request->toData());
        } catch (Throwable $e) {
            // The sync talks to the server, so failure is an input problem
            // (wrong URL or token), not a field error — same 422 shape as the
            // dashboard's connector screen.
            $message = $e instanceof McpException
                ? $e->getMessage()
                : __('Could not connect to the MCP server: :reason', ['reason' => $e->getMessage()]);

            return response()->json(['message' => $message], 422);
        }

        return response()->json(['data' => McpServerData::fromModel($result->server)], 201);
    }

    /**
     * Get connector
     *
     * @return array{data: McpServerData}
     */
    public function show(AgentMcpServer $mcp): array
    {
        // Built-in servers carry no workspace and pass `workspace.scope`
        // untouched; they are the platform's, so they do not exist here.
        abort_if($mcp->workspace_id === null, 404);

        return ['data' => McpServerData::fromModel($mcp)];
    }

    /**
     * Delete connector
     *
     * Deletes a custom MCP server and removes it from every agent it's
     * attached to. Catalog connectors are disconnected in the dashboard (404
     * here).
     */
    public function destroy(AgentMcpServer $mcp): Response
    {
        abort_unless($mcp->isCustom(), 404);

        $mcp->delete();

        return response()->noContent();
    }
}
