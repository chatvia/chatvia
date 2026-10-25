<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Perplexity;

use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The `research` tool: deep multi-source research on a Sonar reasoning model.
 */
#[Name('research')]
#[Description('Deep, comprehensive web research across many sources, returning a detailed and well-cited report. Slower than web_search or ask — use only for complex, multi-faceted topics that genuinely need depth.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld]
class PerplexityResearchTool extends PerplexitySonarTool
{
    protected function model(): string
    {
        return (string) config('services.perplexity.research_model');
    }

    protected function systemPrompt(): ?string
    {
        return 'Research the topic in depth across multiple high-quality sources. Produce a structured, comprehensive answer and cite every claim.';
    }

    protected function timeout(): int
    {
        return 90;
    }
}
