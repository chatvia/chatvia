<?php

namespace Domain\Integration\Mcp\Services\Servers;

use Domain\Integration\Mcp\Services\Tools\PerplexityAskTool;
use Domain\Integration\Mcp\Services\Tools\PerplexityFetchUrlTool;
use Domain\Integration\Mcp\Services\Tools\PerplexityFinanceSearchTool;
use Domain\Integration\Mcp\Services\Tools\PerplexityPeopleSearchTool;
use Domain\Integration\Mcp\Services\Tools\PerplexityResearchTool;
use Domain\Integration\Mcp\Services\Tools\PerplexityRunCodeTool;
use Domain\Integration\Mcp\Services\Tools\PerplexityWebSearchTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

#[Name('Perplexity')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
Live web access powered by Perplexity. Every tool returns a cited answer plus
the source URLs. Pick the lightest one that fits the question:
- web_search: quick factual lookups — recent events, prices, news, anything that may have changed since training.
- fetch_url: read a specific page when you already have its URL (not a search).
- finance_search: real-time market data — stock quotes, earnings, company fundamentals.
- people_search: structured public profile data about a person (who they are, role, company).
- ask: a thorough, reasoned answer when the question needs explanation, comparison, or step-by-step reasoning.
- research: deep, multi-source research for complex topics that need a detailed report (slower — use sparingly).
- run_code: execute code in an isolated remote sandbox for calculations, parsing, or data analysis.
TXT)]
class PerplexityServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        PerplexityWebSearchTool::class,
        PerplexityFetchUrlTool::class,
        PerplexityFinanceSearchTool::class,
        PerplexityPeopleSearchTool::class,
        PerplexityAskTool::class,
        PerplexityResearchTool::class,
        PerplexityRunCodeTool::class,
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
