<?php

namespace Domain\Integration\Mcp\Services\Servers;

use Domain\Integration\Mcp\Services\Tools\WebSearchTool;
use Laravel\Mcp\Server;
use Laravel\Mcp\Server\Attributes\Instructions;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Attributes\Version;
use Laravel\Mcp\Server\Prompt;
use Laravel\Mcp\Server\Tool;

#[Name('Web Search')]
#[Version('1.0.0')]
#[Instructions(<<<'TXT'
Live web search powered by Mistral's web_search built-in. Exposes a single tool:
- web-search: search the web for a query and return a synthesized answer plus the source URLs.
Use this when the user asks about recent events, current prices, news, or anything that may have changed since training.
TXT)]
class WebSearchServer extends Server
{
    /**
     * @var array<int, class-string<Tool>>
     */
    protected array $tools = [
        WebSearchTool::class,
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
