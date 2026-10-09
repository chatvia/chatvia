<?php

declare(strict_types=1);

namespace Domain\Integration\Mcp\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Derived from `tools/list` annotations on each catalog refresh:
 *   - readOnlyHint === true → Read
 *   - otherwise              → Write
 *
 * Drives the section split in the Connectors UI ("Read-only" vs
 * "Write/delete"). Not user-editable — it's a property of the tool itself,
 * not a permission decision.
 */
#[TypeScript]
enum McpToolScope: string
{
    case Read = 'read';
    case Write = 'write';
}
