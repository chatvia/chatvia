<?php

namespace App\Http\Controllers\Settings\Connectors\Manage;

use App\Auth\Models\User;
use App\Http\Controllers\Controller;
use Domain\Agents\Data\Resources\Mcp\AgentMcpServerData;
use Domain\Agents\Models\AgentMcpServer;
use Domain\Connectors\Actions\Mcp\ToggleMcpServerAction;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Flip an account-level MCP/connector's `enabled` flag.
 */
class ToggleConnectorController extends Controller
{
    public function __invoke(Request $request, string $slug, ToggleMcpServerAction $toggle): JsonResponse
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
