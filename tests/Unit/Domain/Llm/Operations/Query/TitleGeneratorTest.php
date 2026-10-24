<?php

declare(strict_types=1);

use Domain\Llm\Operations\Query\TitleGenerator;
use Domain\Llm\Services\LlmChat;
use Domain\Llm\Services\TaskModels;
use Illuminate\Support\Str;
use Tests\Support\Llm\CannedLlmProvider;

/**
 * Conversation titles: the model's reply is cleaned to one short line, and the
 * call leaves a reasoning model room to think — with a tight token cap it
 * stops before writing anything and the title is empty.
 */
it('cleans the reply to a short title', function (): void {
    expect(TitleGenerator::clean("**Title:** \"Sunday Opening Hours.\"\n\nThe visitor asked when…"))->toBe('Sunday Opening Hours')
        ->and(TitleGenerator::clean("\n\n# Polite Decline\n"))->toBe('Polite Decline')
        ->and(TitleGenerator::clean('Title: Order tracking'))->toBe('Order tracking')
        ->and(Str::length(TitleGenerator::clean(Str::repeat('word ', 40))))->toBeLessThanOrEqual(80)
        ->and(TitleGenerator::clean(''))->toBe('')
        ->and(TitleGenerator::clean("  \n "))->toBe('');
});

it('gives the model room to reason before it writes the title', function (): void {
    testLlm();
    $provider = CannedLlmProvider::answering("Sunday Opening Hours\n\n");

    $title = new TitleGenerator(app(LlmChat::class)->using($provider), app(TaskModels::class))
        ->generate("When are you open on Sunday?\nFrom nine to five.");

    expect($title)->toBe('Sunday Opening Hours')
        ->and($provider->completions)->toHaveCount(1)
        ->and($provider->completions[0]['max_tokens'] ?? $provider->completions[0]['max_completion_tokens'] ?? 0)->toBeGreaterThanOrEqual(300);
});
