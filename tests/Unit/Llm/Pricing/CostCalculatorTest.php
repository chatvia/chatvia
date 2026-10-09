<?php

use Domain\Llm\Data\Chat\ChatUsageData;
use Domain\Llm\Operations\Pricing\CostCalculator;

/**
 * CostCalculator turns token counts into cents. It must not round to whole
 * hundredths of a cent before the value is stored as micro-cents, or almost
 * every single-call cost collapses to zero.
 */
it('keeps sub-cent precision instead of rounding to two decimals', function (): void {
    // 1,000 input tokens at $0.90 / 1M = 0.09 cents; 200 output tokens at $0.90 / 1M = 0.018 cents.
    $cents = (new CostCalculator)->costInCents(1_000, 200, 0.90, 0.90);

    expect($cents)->toEqualWithDelta(0.108, 1e-12)
        ->and(CostCalculator::centsToMicroCents($cents))->toBe(108_000);
});

it('does not turn a small embedding cost into zero', function (): void {
    // ~10 tokens at $0.10 / 1M is about 0.0001 cents — far below 0.01.
    $cents = (new CostCalculator)->calculateCostInCents('a short sentence to embed here', '', 0.10, 0.0);

    expect($cents)->toBeGreaterThan(0.0)
        ->and(CostCalculator::centsToMicroCents($cents))->toBeGreaterThan(0);
});

it('prices a call from the provider usage block', function (): void {
    $cents = (new CostCalculator)->costInCentsFromUsage(
        ChatUsageData::fromUsage(['prompt_tokens' => 2_000, 'completion_tokens' => 500, 'total_tokens' => 2_500]),
        2.0,
        8.0,
    );

    // 2,000 × 2.0 / 1M × 100 = 0.4 cents; 500 × 8.0 / 1M × 100 = 0.4 cents.
    expect($cents)->toEqualWithDelta(0.8, 1e-12);
});

it('treats a missing usage block as zero tokens', function (): void {
    expect((new CostCalculator)->costInCentsFromUsage(null, 2.0, 8.0))->toBe(0.0);
});
