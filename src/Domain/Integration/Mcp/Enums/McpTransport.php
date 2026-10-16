<?php

declare(strict_types=1);

namespace Domain\Integration\Mcp\Enums;

use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * Wire protocol used to reach an MCP server.
 */
#[TypeScript]
enum McpTransport: string
{
    case StreamableHttp = 'streamable_http';
    case HttpSse = 'http_sse';
}
