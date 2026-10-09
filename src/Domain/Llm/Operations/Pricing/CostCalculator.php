<?php

declare(strict_types=1);

namespace Domain\Llm\Operations\Pricing;

use Domain\Llm\Data\Chat\ChatUsageData;
use Domain\Llm\Services\TikToken;

/**
 * Turns token counts and per-million-token prices into a cost in cents.
 * Costs stay unrounded floats here; the single rounding step happens when
 * they are stored as integer micro-cents.
 */
class CostCalculator
{
    /**
     * Estimate a cost by counting tokens in the given text. Use this only when
     * the provider reports no `usage` (for example embeddings).
     */
    public function calculateCostInCents(string $inputText, string $outputText = '', float $pricePerInputMilToken = 0.40, float $pricePerOutputMilToken = 1.60): float
    {
        return $this->costInCents(
            inputTokens: TikToken::countTokens($inputText),
            outputTokens: $outputText === '' ? 0 : TikToken::countTokens($outputText),
            pricePerInputMilToken: $pricePerInputMilToken,
            pricePerOutputMilToken: $pricePerOutputMilToken,
        );
    }

    /**
     * Cost of one LLM call from the token usage the provider reported, which is
     * what the provider bills. No usage means no cost.
     */
    public function costInCentsFromUsage(?ChatUsageData $usage, float $pricePerInputMilToken, float $pricePerOutputMilToken): float
    {
        return $this->costInCents(
            inputTokens: $usage->promptTokens ?? 0,
            outputTokens: $usage->completionTokens ?? 0,
            pricePerInputMilToken: $pricePerInputMilToken,
            pricePerOutputMilToken: $pricePerOutputMilToken,
        );
    }

    /**
     * Prices are in dollars (or euros) per million tokens; the result is in
     * cents of the same currency.
     */
    public function costInCents(int $inputTokens, int $outputTokens, float $pricePerInputMilToken, float $pricePerOutputMilToken): float
    {
        $inputRate = $pricePerInputMilToken * 100 / 1_000_000;
        $outputRate = $pricePerOutputMilToken * 100 / 1_000_000;

        return ($inputTokens * $inputRate) + ($outputTokens * $outputRate);
    }

    /**
     * Convert a cents float (output of calculateCostInCents) into integer
     * micro-cents for storage. 1 cent = 10^6 micro-cents.
     */
    public static function centsToMicroCents(float $cents): int
    {
        return (int) round($cents * 1_000_000);
    }

    /**
     * Convert a dollar amount (e.g. an OpenRouter usage.total_cost field)
     * into integer micro-cents. 1 dollar = 10^8 micro-cents.
     */
    public static function dollarsToMicroCents(float $dollars): int
    {
        return (int) round($dollars * 100_000_000);
    }
}
