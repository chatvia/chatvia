<?php

declare(strict_types=1);

namespace Domain\Integration\Tool\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Which backend runs a tool: an MCP server (named `mcp__{slug}__{tool}`) or a
 * webhook function (any other name).
 *
 * The value is part of the wire format: it rides on the SSE tool events, is
 * stored in the pending calls of a ToolApprovalCheckpoint and in the
 * governance audit, so the strings must not change.
 */
#[TypeScript]
enum ToolKind: string
{
    case Mcp = 'mcp';
    case Function = 'function';
}
