<?php

namespace Domain\Integration\Mcp\Services\Servers;

use Domain\Integration\Mcp\Services\Tools\CaptureLeadTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

#[Name('Leads')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
Capture contact details for visitors who want to be reached by the team. Exposes a single tool:
- capture-lead: persist the visitor's name, email, and any other details they share against the current conversation.

Call this tool only when the visitor explicitly asks to be contacted, requests a quote, demo, or callback, or volunteers their contact information unprompted. Never invent values — ask the visitor for missing details first. The configured agent decides which fields are required; the tool returns a clear error listing any missing required fields so you can ask the visitor and try again.
TXT)]
class LeadsServer extends Server
{
    /**
     * The tools registered with this MCP server.
     *
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        CaptureLeadTool::class,
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
