<?php

namespace Domain\Integration\Mcp\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * One connector in an audit page's filter dropdown: the stored slug plus the
 * display name of the MCP server it came from.
 */
#[TypeScript]
class ConnectorOptionData extends Data
{
    public function __construct(
        public string $slug,
        public ?string $name,
    ) {}
}
