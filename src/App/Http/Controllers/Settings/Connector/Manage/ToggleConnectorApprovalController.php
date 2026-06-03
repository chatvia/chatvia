<?php

namespace App\Http\Controllers\Settings\Connector\Manage;

use App\Http\Controllers\Controller;
use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Actions\ToggleConnectorApprovalAction;
use Domain\Integration\Mcp\Data\Resources\AgentMcpServerData;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Flip the master "require approval" switch on an account-level connector.
 */
class ToggleConnectorApprovalController extends Controller
{
    public function __invoke(Request $request, string $slug, ToggleConnectorApprovalAction $toggle): JsonResponse
    {
        abort_unless(has_team_permission('account.connectors'), 404);

        $server = $toggle->execute($this->find($request, $slug));

        return response()->json(['data' => AgentMcpServerData::fromModel($server)]);
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
