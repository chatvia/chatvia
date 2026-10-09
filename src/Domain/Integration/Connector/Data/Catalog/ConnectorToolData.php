<?php

declare(strict_types=1);

namespace Domain\Integration\Connector\Data\Catalog;

use Domain\Integration\Mcp\Enums\McpToolPermission;
use Domain\Integration\Mcp\Enums\McpToolScope;
use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One tool inside a connector's tool catalog, projected onto the shape the
 * Connectors sheet uses: prefixed name + raw short name + tri-state
 * permission + read/write scope (drives the section split).
 */
#[TypeScript]
class ConnectorToolData extends Data
{
    public function __construct(
        public int $id,
        public string $name,
        public string $short_name,
        public ?string $description,
        public mixed $parameters,
        public McpToolScope $scope,
        public McpToolPermission $permission,
    ) {}
}
