<?php

namespace App\Http\Controllers\Settings\Connectors;

use App\Auth\Models\User;
use App\Http\Controllers\Controller;
use Domain\Agents\Models\AgentMcpServer;
use Domain\Connectors\Actions\ToggleMcpToolAction;
use Domain\Connectors\Data\ToggledConnectorToolData;
use Illuminate\Http\Request;
use RuntimeException;

/**
 * Flip an individual tool's `enabled` flag inside an account-level MCP/connector.
 */
class ToggleConnectorToolController extends Controller
{
    public function __invoke(Request $request, string $slug, int $tool, ToggleMcpToolAction $toggle): ToggledConnectorToolData
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
