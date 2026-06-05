<?php

namespace Domain\Integration\Mcp\Enums;

/**
 * Per-tool permission for an MCP tool. Surfaced as a tri-state in the
 * Connectors UI and read by the catalog/registry + approval policy:
 *
 *   - Allow    → tool runs inline, no prompt
 *   - Approval → tool pauses for the user via ToolApprovalCheckpoint
 *   - Blocked  → tool is excluded from the catalog shown to the LLM
 */
enum McpToolPermission: string
{
    case Allow = 'allow';
    case Approval = 'approval';
    case Blocked = 'blocked';
}
