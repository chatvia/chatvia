<?php

namespace App\Http\Controllers\Settings\Connector\Manage;

use App\Http\Requests\Settings\Connector\SetConnectorToolsScopePermissionRequest;
use Domain\Integration\Mcp\Actions\SetMcpToolsScopePermissionAction;
use Domain\Integration\Mcp\Data\McpToolsScopePermissionData;
use Domain\Integration\Mcp\Models\AgentMcpServer;

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
        // An unknown slug 404s via forSlugScoped; the action itself only
        // reports how many tools changed.
        return $set->execute(
            AgentMcpServer::forSlugScoped($slug, $request->user()),
            $request->scope(),
            $request->permission(),
        );
    }
}
