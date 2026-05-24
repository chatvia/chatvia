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
}
