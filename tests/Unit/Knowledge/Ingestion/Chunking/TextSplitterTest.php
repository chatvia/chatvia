<?php

use Domain\Knowledge\Ingestion\Data\Chunking\ChunkingConfigData;
use Domain\Knowledge\Ingestion\Enums\ChunkSizeUnit;
use Domain\Knowledge\Ingestion\Enums\ContentKind;
use Domain\Knowledge\Ingestion\Enums\SplitStrategy;
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

    // The Markdown strategy repeats the heading above every chunk of the section.
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

it('splits transcripts into whole sentences by default', function (): void {
    $transcript = 'Welcome to the show. Today we talk about tents. They keep you dry. Bring one on every trip.';

    expect((new BuiltinTextSplitter)->split($transcript, ContentKind::Transcript))->toBe([
        'Welcome to the show. Today we talk about tents.',
        'They keep you dry. Bring one on every trip.',
    ]);
});

it('cleans invalid UTF-8 and returns nothing for blank text', function (): void {
    $splitter = new BuiltinTextSplitter;

    expect($splitter->split("Good text \xC3\x28 here.", ContentKind::Text))->toBe(['Good text ?( here.'])
        ->and($splitter->split(" \n\n \t", ContentKind::Text))->toBe([]);
});

it('splits with an unsaved setup, such as the admin preview', function (): void {
    $config = new ChunkingConfigData(
        kind: ContentKind::Text,
        strategy: SplitStrategy::Fixed,
        unit: ChunkSizeUnit::Characters,
        size: 4,
        overlap: 1,
    );

    expect((new BuiltinTextSplitter)->splitWith('abcdefghij', $config))->toBe(['abcd', 'defg', 'ghij']);
});

it('is named builtin', function (): void {
    expect((new BuiltinTextSplitter)->name())->toBe('builtin');
});
