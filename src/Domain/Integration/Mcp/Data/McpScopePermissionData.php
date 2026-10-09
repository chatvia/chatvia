<?php

declare(strict_types=1);

namespace Domain\Integration\Mcp\Data;

use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Enums\McpToolScope;
use Spatie\LaravelData\Data;

/**
 * A permission for every tool in one scope (read or write) of a connector —
 * what the section dropdown in the Connectors UI asks to set. The result,
 * with the number of tools changed, is {@see McpToolsScopePermissionData}.
 */
class McpScopePermissionData extends Data
{
    public function __construct(
        public McpToolScope $scope,
        public McpToolPermission $permission,
    ) {}
}
