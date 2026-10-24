<?php

declare(strict_types=1);

use Domain\Llm\Operations\Documents\Tables;
use Domain\Llm\Services\LlmChat;
use Domain\Llm\Services\TaskModels;
use Tests\Support\Llm\CannedLlmProvider;

/**
 * Table descriptions: the call leaves a reasoning model room to think and
 * still write prose for every table on the page — with a tight token cap the
 * description is cut short or comes back empty.
 */
it('gives the model room to reason before it describes the tables', function (): void {
    testLlm();
    $provider = CannedLlmProvider::answering('Table 1: The Basic plan costs 9 EUR per month.');

    $description = new Tables(app(LlmChat::class)->using($provider), app(TaskModels::class))
        ->describe(["| Plan | Price |\n|---|---|\n| Basic | 9 EUR |"]);

    expect($description)->toBe('Table 1: The Basic plan costs 9 EUR per month.')
        ->and($provider->completions)->toHaveCount(1)
        ->and($provider->completions[0]['max_tokens'] ?? $provider->completions[0]['max_completion_tokens'] ?? 0)->toBeGreaterThanOrEqual(1500);
});
