<?php

declare(strict_types=1);

namespace App\Mcp\Tools\Perplexity;

use Illuminate\Contracts\JsonSchema\JsonSchema;
use Laravel\Mcp\Server\Attributes\Description;
use Laravel\Mcp\Server\Attributes\Name;
use Laravel\Mcp\Server\Tools\Annotations\IsIdempotent;
use Laravel\Mcp\Server\Tools\Annotations\IsOpenWorld;
use Laravel\Mcp\Server\Tools\Annotations\IsReadOnly;

/**
 * The `finance_search` tool: real-time market and company financial data.
 */
#[Name('finance_search')]
#[Description('Look up real-time market and company financial data — stock quotes, financials, earnings, market movers. Use for questions about tickers, share prices, or company fundamentals; use web_search for general news.')]
#[IsReadOnly]
#[IsIdempotent]
#[IsOpenWorld]
class PerplexityFinanceSearchTool extends PerplexityAgentTool
{
    protected function toolType(): string
    {
        return 'finance_search';
    }

    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()
                ->description('The financial question, naming the company or ticker, e.g. "NVDA current share price" or "Apple latest quarterly earnings".')
                ->required(),
        ];
    }
}
