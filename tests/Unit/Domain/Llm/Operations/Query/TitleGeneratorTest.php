<?php

declare(strict_types=1);

use Domain\Llm\Operations\Query\TitleGenerator;
use Illuminate\Support\Str;

/**
 * Conversation titles: the model's reply is cleaned to one short line.
 */
it('cleans the reply to a short title', function (): void {
    expect(TitleGenerator::clean("**Title:** \"Sunday Opening Hours.\"\n\nThe visitor asked when…"))->toBe('Sunday Opening Hours')
        ->and(TitleGenerator::clean("\n\n# Polite Decline\n"))->toBe('Polite Decline')
        ->and(TitleGenerator::clean('Title: Order tracking'))->toBe('Order tracking')
        ->and(Str::length(TitleGenerator::clean(Str::repeat('word ', 40))))->toBeLessThanOrEqual(80)
        ->and(TitleGenerator::clean(''))->toBe('')
        ->and(TitleGenerator::clean("  \n "))->toBe('');
});
