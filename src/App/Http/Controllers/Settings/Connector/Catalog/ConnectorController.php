<?php

namespace App\Http\Controllers\Settings\Connector\Catalog;

use Domain\Integration\Connector\Actions\Account\ListAccountConnectorsAction;
use Domain\Integration\Connector\Data\Account\AccountConnectorsResourceData;
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
class ConnectorController
{
    public function index(Request $request, ListAccountConnectorsAction $list): AccountConnectorsResourceData
    {
        return $list->execute($request->user());
    }

    public function edit(): Response
    {
        return Inertia::render('workspace/integrations/Connectors');
    }

    public function destroy(Request $request, string $slug): JsonResponse
    {
        AgentMcpServer::forSlugScoped($slug, $request->user())->delete();

        return response()->json(['ok' => true]);
    }
}
