<?php

namespace App\Http\Controllers\Settings\Connectors\Catalog;

use App\Auth\Models\User;
use App\Http\Controllers\Controller;
use Domain\Agents\Models\AgentMcpServer;
use Domain\Connectors\Actions\Account\ListAccountConnectorsAction;
use Domain\Connectors\Actions\Mcp\DeleteMcpServerAction;
use Domain\Connectors\Data\AccountConnectorsResourceData;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Renders the Settings → Connectors page, serves the JSON payload that powers
 * its rows, and disconnects (deletes) account-level MCP servers. Per-server
 * mutations live in dedicated invokable controllers (toggle, refresh, …).
 */
class ConnectorsController extends Controller
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

        $delete->execute($this->find($request, $slug));

        return response()->json(['ok' => true]);
    }

    private function find(Request $request, string $slug): AgentMcpServer
    {
        /** @var User $user */
        $user = $request->user();
        $server = $user->mcpServers()->where('slug', $slug)->first();

        abort_unless($server !== null, 404);

        return $server;
    }
}
