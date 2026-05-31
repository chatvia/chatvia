<?php

namespace App\LLM\Operations;

use Domain\Ingestion\Services\TikToken;

class CostCalculator
{
    public function calculateCostInCents(string $inputText, string $outputText = '', float $pricePerInputMilToken = 0.40, float $pricePerOutputMilToken = 1.60): float
    {
        $inputRate = $pricePerInputMilToken * 100 / 1_000_000;
        $outputRate = $pricePerOutputMilToken * 100 / 1_000_000;

        $inputCost = TikToken::countTokens($inputText) * $inputRate;
        $outputCost = TikToken::countTokens($outputText) * $outputRate;

        return round($inputCost + $outputCost, 2);
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
