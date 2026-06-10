<?php

namespace Domain\Integration\Mcp\Services\Tools;

use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

#[Name('ask')]
#[Description('Ask Perplexity a question and get a thorough, reasoned answer synthesized from live web sources. Use when the question needs explanation, comparison, or step-by-step reasoning — not just a single fact.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld]
class PerplexityAskTool extends PerplexitySonarTool
{
    protected function model(): string
    {
        return (string) config('services.perplexity.ask_model', 'sonar-pro');
    }

    protected function systemPrompt(): ?string
    {
        return 'Answer the question thoroughly and accurately using live web sources. Explain the key reasoning and cite every factual claim.';
    }
}
