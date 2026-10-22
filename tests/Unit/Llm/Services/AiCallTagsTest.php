<?php

use Domain\Llm\Enums\AiCallTag;
use Domain\Llm\Services\AiCallTags;
use Spectra\Spectra;

/**
 * An AI call sent inside `during()` carries its tag in Spectra, and only
 * that call: the tag is gone once the callback ends, nested tags add up,
 * and nothing is tagged while Spectra does not record.
 */
function actNextTags(): array
{
    return app(Spectra::class)->startRequest('openai', 'gpt-test')->tags;
}

it('tags the calls sent inside the callback, and only those', function (): void {
    config(['spectra.enabled' => true]);

    $inside = app(AiCallTags::class)->during(AiCallTag::ConversationTitle, actNextTags(...));

    expect($inside)->toBe(['conversation-title'])
        ->and(actNextTags())->toBe([]);
});

it('adds nested tags to the outer ones', function (): void {
    config(['spectra.enabled' => true]);
    $tags = app(AiCallTags::class);

    $inside = $tags->during(AiCallTag::Evaluation, fn (): array => $tags->during(AiCallTag::ChatReply, actNextTags(...)));

    expect($inside)->toBe(['evaluation', 'chat-reply']);
});

it('only runs the callback while Spectra does not record', function (): void {
    config(['spectra.enabled' => false]);

    expect(app(AiCallTags::class)->during(AiCallTag::Rerank, actNextTags(...)))->toBe([]);
});

it('returns what the callback returns', function (): void {
    config(['spectra.enabled' => true]);

    expect(app(AiCallTags::class)->during(AiCallTag::Rerank, fn (): int => 7))->toBe(7);
});
