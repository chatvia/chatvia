<?php

declare(strict_types=1);

namespace Domain\Integration\Mcp\Enums;

/**
 * Classifies every `mcp_servers` row by origin/purpose:
 *
 *   - Custom       — the workspace's own MCP server (bring-your-own URL).
 *   - Integration  — a public external service (Stripe, Notion, Salesforce, and
 *                    the token-keyed Chatvia-hosted ones: Weather, Currency, Perplexity).
 *   - Builtin      — Chatvia first-party with no external dependency: Leads, Interactive Charts.
 *   - Analytics    — the owner-facing "Ask your data" server: account-analytics.
 *
 * The global (`workspace_id IS NULL`) rows of category Analytics + Builtin back the
 * agent-less analytics chat's tool routing.
 */
enum McpServerCategory: string
{
    case Custom = 'custom';
    case Integration = 'integration';
    case Builtin = 'builtin';
    case Analytics = 'analytics';
}
