<?php

declare(strict_types=1);

namespace App\Mcp\Servers;

use App\Mcp\Tools\Perplexity\PerplexityAskTool;
use App\Mcp\Tools\Perplexity\PerplexityFetchUrlTool;
use App\Mcp\Tools\Perplexity\PerplexityFinanceSearchTool;
use App\Mcp\Tools\Perplexity\PerplexityPeopleSearchTool;
use App\Mcp\Tools\Perplexity\PerplexityResearchTool;
use App\Mcp\Tools\Perplexity\PerplexityRunCodeTool;
use App\Mcp\Tools\Perplexity\PerplexityWebSearchTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

/**
 * The Perplexity connector: live, cited web answers (bring-your-own key).
 */
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
    #[\Override]
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
    #[\Override]
    protected array $resources = [];

    /**
     * @var array<int, class-string<Prompt>>
     */
    #[\Override]
    protected array $prompts = [];
}
