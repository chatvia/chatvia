<?php

namespace App\Http\Controllers\Settings\Connector\Manage;

use App\Http\Requests\Settings\Connector\SetConnectorToolsScopePermissionRequest;
use Domain\Integration\Mcp\Actions\SetMcpToolsScopePermissionAction;
use Domain\Integration\Mcp\Data\McpToolsScopePermissionData;
use Domain\Integration\Mcp\Models\AgentMcpServer;
use RuntimeException;

/**
 * Bulk-set the tri-state permission (allow / approval / blocked) on every tool
 * in one scope (read or write) of an account-level connector — the section
 * "apply to all" action in the Connectors UI.
 */
class SetConnectorToolsScopePermissionController
{
    public function __invoke(
        SetConnectorToolsScopePermissionRequest $request,
        string $slug,
        SetMcpToolsScopePermissionAction $set,
    ): McpToolsScopePermissionData {
        try {
            return $set->execute(
                AgentMcpServer::forSlugScoped($slug, $request->user()),
                $request->scope(),
                $request->permission(),
            );
        } catch (RuntimeException) {
            abort(404);
        }
    }
}
