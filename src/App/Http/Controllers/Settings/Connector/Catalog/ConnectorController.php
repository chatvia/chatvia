<?php

namespace App\Http\Controllers\Settings\Connector\Catalog;

use App\Http\Controllers\Controller;
use Domain\Integration\Connector\Actions\Account\ListAccountConnectorsAction;
use Domain\Integration\Connector\Data\Account\AccountConnectorsResourceData;
use Domain\Integration\Mcp\Actions\DeleteMcpServerAction;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the Settings → Connectors page, serves the JSON payload that powers
 * its rows, and disconnects (deletes) account-level MCP servers. Per-server
 * mutations live in dedicated invokable controllers (toggle, refresh, …).
 */
class ConnectorController extends Controller
{
    public function index(Request $request, ListAccountConnectorsAction $list): AccountConnectorsResourceData
    {
        abort_unless(has_team_permission('account.connectors'), 404);

        return $list->execute($request->user());
    }

    public function edit(): Response
    {
        abort_unless(has_team_permission('account.connectors'), 404);

        return Inertia::render('settings/Connectors');
    }

    public function destroy(Request $request, string $slug, DeleteMcpServerAction $delete): JsonResponse
    {
        abort_unless(has_team_permission('account.connectors'), 404);

        $delete->execute(AgentMcpServer::forSlugScoped($slug, $request->user()));

        return response()->json(['ok' => true]);
    }
}
