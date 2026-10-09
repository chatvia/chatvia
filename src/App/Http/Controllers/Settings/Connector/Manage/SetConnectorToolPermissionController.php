<?php

namespace App\Http\Controllers\Settings\Connector\Manage;

use App\Http\Requests\Settings\Connector\SetConnectorToolPermissionRequest;
use Domain\Integration\Mcp\Actions\Permissions\SetMcpToolPermissionAction;
use Domain\Integration\Mcp\Data\McpToolPermissionData;
use Domain\Integration\Mcp\Exceptions\McpToolNotFoundException;
use Domain\Integration\Mcp\Models\AgentMcpServer;

/**
 * Set the tri-state permission (allow / approval / blocked) on a single tool
 * inside an account-level connector.
 */
class SetConnectorToolPermissionController
{
    public function __invoke(
        SetConnectorToolPermissionRequest $request,
        string $slug,
        SetMcpToolPermissionAction $set,
    ): McpToolPermissionData {
        $server = AgentMcpServer::forSlugScoped($slug, $request->user());

        try {
            return $set->execute($server, $request->toData());
        } catch (McpToolNotFoundException) {
            abort(404);
        }
    }
}
