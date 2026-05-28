<?php

namespace Support\Enums;

/**
 * Wire protocol used to reach an MCP server.
 */
enum McpTransport: string
{
    case StreamableHttp = 'streamable_http';
    case HttpSse = 'http_sse';
}
