<?php

namespace Domain\Connectors\Data\Catalog;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One tool inside a connector's tool catalog, projected onto the shape the
 * Connectors sheet uses (prefixed name + raw short name + enable flag).
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
        public bool $is_enabled,
    ) {}
}
