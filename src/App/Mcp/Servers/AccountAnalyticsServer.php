<?php

namespace App\Mcp\Servers;

use App\Mcp\Tools\Analytics\GetAccountOverviewTool;
use App\Mcp\Tools\Analytics\GetActivityPatternsTool;
use App\Mcp\Tools\Analytics\GetAgentBreakdownTool;
use App\Mcp\Tools\Analytics\GetAiUsageTool;
use App\Mcp\Tools\Analytics\GetBillingCycleUsageTool;
use App\Mcp\Tools\Analytics\GetChannelBreakdownTool;
use App\Mcp\Tools\Analytics\GetConversationStatsTool;
use App\Mcp\Tools\Analytics\GetGeographyStatsTool;
use App\Mcp\Tools\Analytics\GetHandoffStatsTool;
use App\Mcp\Tools\Analytics\GetLeadStatsTool;
use App\Mcp\Tools\Analytics\GetMessageStatsTool;
use App\Mcp\Tools\Analytics\GetResponseTimeStatsTool;
use App\Mcp\Tools\Analytics\GetSatisfactionStatsTool;
use App\Mcp\Tools\Analytics\GetTokenUsageTool;
use App\Mcp\Tools\Analytics\GetTopTopicsTool;
use App\Mcp\Tools\Analytics\GetTopUsersTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

/**
 * INTERNAL, owner-scoped analytics server. Unlike the other built-in servers
 * (Charts, Weather, Forms, ...) this one is deliberately NOT registered as a
 * featured connector in config/connectors.php — it never appears in the
 * customer-facing Connectors catalogue and is never attached to an agent. It is
 * driven only by the owner-facing "Ask your data" chat, which signs each call
 * with `X-Chatvia-Workspace-Id`; every tool scopes its numbers to that
 * workspace. See routes/ai.php and VerifyInternalMcpRequest (workspace mode).
 */
#[Name('Account Analytics')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
Answer the workspace owner's questions about their own Chatvia account in
numbers. Every tool takes a `period` (default "current_cycle" — the current
billing cycle) and returns both a short text answer and exact figures.

Pick the tool that matches the question:
- get-account-overview     — a one-shot snapshot of everything
- get-message-stats        — message volume, in vs out, trend, per-day series
- get-billing-cycle-usage  — messages used vs the plan limit, days left, projection
- get-conversation-stats   — conversation count, length, duration, busiest day
- get-top-users            — most active end-users by messages; unique/new visitors
- get-channel-breakdown    — conversations per channel (web, WhatsApp, Telegram, ...)
- get-agent-breakdown      — activity per agent
- get-geography-stats      — top countries; device/browser/OS mix
- get-activity-patterns    — peak hour and busiest weekday
- get-satisfaction-stats   — thumbs up/down, sentiment, unanswered rate, confidence
- get-response-time-stats  — average / p95 / first-response latency
- get-top-topics           — what visitors ask about most
- get-lead-stats           — leads captured, by status, conversion rate
- get-handoff-stats        — escalations to a human, handoff rate, pending now
- get-token-usage          — tokens consumed, prompt/completion split, per model
- get-ai-usage             — model, top functions, MCP tools, knowledge sources

Lead with the number. When a result is a series or breakdown, or the user wants
a visual, pass the figures to a charts tool to render an interactive chart.
TXT)]
class AccountAnalyticsServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        GetAccountOverviewTool::class,
        GetMessageStatsTool::class,
        GetBillingCycleUsageTool::class,
        GetConversationStatsTool::class,
        GetTopUsersTool::class,
        GetChannelBreakdownTool::class,
        GetAgentBreakdownTool::class,
        GetGeographyStatsTool::class,
        GetActivityPatternsTool::class,
        GetSatisfactionStatsTool::class,
        GetResponseTimeStatsTool::class,
        GetTopTopicsTool::class,
        GetLeadStatsTool::class,
        GetHandoffStatsTool::class,
        GetTokenUsageTool::class,
        GetAiUsageTool::class,
    ];

    /**
     * @var array<int, class-string<Server\Resource>>
     */
    protected array $resources = [];

    /**
     * @var array<int, class-string<Prompt>>
     */
    protected array $prompts = [];
}
