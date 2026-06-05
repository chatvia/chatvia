<?php

namespace App\Http\Controllers\Settings\Connector\Manage;

use App\Http\Controllers\Controller;
use App\Http\Requests\Settings\Connector\SetConnectorToolPermissionRequest;
use Domain\Integration\Mcp\Actions\SetMcpToolPermissionAction;
use Domain\Integration\Mcp\Data\McpToolPermissionData;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use RuntimeException;

/**
 * Set the tri-state permission (allow / approval / blocked) on a single tool
 * inside an account-level connector.
 */
class SetConnectorToolPermissionController extends Controller
{
    public function __invoke(
        SetConnectorToolPermissionRequest $request,
        string $slug,
        int $tool,
        SetMcpToolPermissionAction $set,
    ): McpToolPermissionData {
        abort_unless(has_team_permission('account.connectors'), 404);

        try {
            return $set->execute(
                AgentMcpServer::forSlugScoped($slug, $request->user()),
                $tool,
                $request->permission(),
            );
        } catch (RuntimeException) {
            abort(404);
        }
    }
}
