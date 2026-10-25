<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Perplexity;

use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The `ask` tool: a thorough, reasoned Sonar answer from live web sources.
 */
#[Name('ask')]
#[Description('Ask Perplexity a question and get a thorough, reasoned answer synthesized from live web sources. Use when the question needs explanation, comparison, or step-by-step reasoning — not just a single fact.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld]
class PerplexityAskTool extends PerplexitySonarTool
{
    protected function model(): string
    {
        return (string) config('services.perplexity.ask_model');
    }

    protected function systemPrompt(): ?string
    {
        return 'Answer the question thoroughly and accurately using live web sources. Explain the key reasoning and cite every factual claim.';
    }
}
