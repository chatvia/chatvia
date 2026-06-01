<?php

namespace Support\Enums\Mcp;

/**
 * How Chatvia authenticates outbound calls to an MCP server.
 */
enum McpAuthType: string
{
    case None = 'none';
    case Bearer = 'bearer';
    case Header = 'header';
}
