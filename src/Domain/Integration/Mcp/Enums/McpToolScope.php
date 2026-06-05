<?php

namespace Domain\Integration\Mcp\Enums;

/**
 * Derived from `tools/list` annotations on each catalog refresh:
 *   - readOnlyHint === true → Read
 *   - otherwise              → Write
 *
 * Drives the section split in the Connectors UI ("Read-only" vs
 * "Write/delete"). Not user-editable — it's a property of the tool itself,
 * not a permission decision.
 */
enum McpToolScope: string
{
    case Read = 'read';
    case Write = 'write';
}
