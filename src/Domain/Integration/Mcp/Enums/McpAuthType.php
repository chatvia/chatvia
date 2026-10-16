<?php

declare(strict_types=1);

namespace Domain\Integration\Mcp\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * How Chatvia authenticates outbound calls to an MCP server.
 */
#[TypeScript]
enum McpAuthType: string
{
    case None = 'none';
    case Bearer = 'bearer';
    case Header = 'header';
}
