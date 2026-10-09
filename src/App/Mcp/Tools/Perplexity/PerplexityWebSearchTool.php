<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Perplexity;

use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The `web_search` tool: a quick, cited web lookup.
 */
#[Name('web_search')]
#[Description('Quick web search for facts, recent events, news, prices, or anything that may have changed since training. Returns a short, cited answer. Use this for simple, single-shot lookups.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld]
class PerplexityWebSearchTool extends PerplexityAgentTool
{
    protected function toolType(): string
    {
        return 'web_search';
    }
}
