<?php

namespace App\Http\Controllers\Settings\Connector\Manage;

use App\Http\Controllers\Controller;
use App\Identity\Workspace\Models\User;
use Domain\Integration\Mcp\Actions\ToggleMcpToolApprovalAction;
use Domain\Integration\Mcp\Data\ToggledConnectorToolData;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Flip the per-tool "requires approval" override inside an account-level
 * connector. Only effective while the connector's master switch is on.
 */
class ToggleConnectorToolApprovalController extends Controller
{
    public function __invoke(Request $request, string $slug, int $tool, ToggleMcpToolApprovalAction $toggle): ToggledConnectorToolData
    {
        abort_unless(has_team_permission('account.connectors'), 404);

        try {
            return $toggle->execute($this->find($request, $slug), $tool);
        } catch (RuntimeException) {
            abort(404);
        }
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
