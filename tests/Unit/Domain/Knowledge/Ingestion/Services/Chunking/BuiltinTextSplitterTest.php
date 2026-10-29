<?php

use Domain\Knowledge\Ingestion\Enums\ContentKind;
use Domain\Knowledge\Ingestion\Services\Chunking\BuiltinTextSplitter;

/**
 * The built-in splitter is the only text splitter: it splits each kind of
 * content with the strategy, unit, size and overlap set in config (Settings
 * → Chunking), in-process.
 */
beforeEach(function (): void {
    config(['chatvia.ai.chunking' => [
        'unit' => 'characters',
        'size' => 60,
        'overlap' => 0,
        'strategies' => ['markdown' => null, 'text' => null, 'transcript' => null],
    ]]);
});

it('splits each kind with the strategy set for it', function (): void {
    $splitter = new BuiltinTextSplitter;
    $markdown = "# Returns\n\nAny item within 30 days. Keep the receipt with you.";

    expect($splitter->split($markdown, ContentKind::Markdown))->toBe([
        "# Returns\n\nAny item within 30 days.",
        "# Returns\n\nKeep the receipt with you.",
    ]);

    config(['chatvia.ai.chunking.strategies.markdown' => 'recursive']);

    expect($splitter->split($markdown, ContentKind::Markdown))->toBe([
        '# Returns',
        'Any item within 30 days. Keep the receipt with you.',
    ]);

    config(['chatvia.ai.chunking.strategies.text' => 'fixed', 'chatvia.ai.chunking.size' => 10]);

    expect($splitter->split('abcdefghijklmnopqrstuvwxy', ContentKind::Text))->toBe(['abcdefghij', 'klmnopqrst', 'uvwxy']);
});

it('cleans invalid UTF-8 and returns nothing for blank text', function (): void {
    $splitter = new BuiltinTextSplitter;

    expect($splitter->split("Good text \xC3\x28 here.", ContentKind::Text))->toBe(['Good text ?( here.'])
        ->and($splitter->split(" \n\n \t", ContentKind::Text))->toBe([]);
});
