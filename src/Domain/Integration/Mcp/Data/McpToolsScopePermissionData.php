<?php

namespace Domain\Integration\Mcp\Data;

use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Enums\McpToolScope;
use Spatie\LaravelData\Data;

/**
 * Result of bulk-setting the permission on every tool in a scope (read or
 * write) of a connector — the scope, its new permission, and how many tool
 * rows were updated (so the page can confirm the change without a refresh).
 */
class McpToolsScopePermissionData extends Data
{
    public function __construct(
        public McpToolScope $scope,
        public McpToolPermission $permission,
        public int $updated,
    ) {}
}
