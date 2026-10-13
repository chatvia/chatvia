<?php

use Domain\Knowledge\Ingestion\Data\Chunking\ChunkingConfigData;
use Domain\Knowledge\Ingestion\Enums\ChunkSizeUnit;
use Domain\Knowledge\Ingestion\Enums\ContentKind;
use Domain\Knowledge\Ingestion\Enums\SplitStrategy;
use Domain\Knowledge\Ingestion\Services\Chunking\Splitting\CharacterMeasure;
use Domain\Knowledge\Ingestion\Services\Chunking\Splitting\TokenMeasure;

/**
 * Each kind of content has its own strategy: the default suits its
 * structure, and the admin may pick another that suits it. The setup of a
 * kind reads the unit, size, overlap and tokenizer from config.
 */
beforeEach(function (): void {
    config([
        'chatvia.ai.chunking' => [
            'unit' => 'tokens',
            'size' => null,
            'overlap' => 0,
            'strategies' => ['markdown' => null, 'text' => null, 'transcript' => null],
        ],
        'chatvia.ai.tokenizer' => 'cl100k_base',
    ]);
});

it('splits Markdown by headings, text by paragraphs, and transcripts by whole sentences, by default', function (): void {
    expect(ContentKind::Markdown->strategy())->toBe(SplitStrategy::Markdown)
        ->and(ContentKind::Text->strategy())->toBe(SplitStrategy::Recursive)
        ->and(ContentKind::Transcript->strategy())->toBe(SplitStrategy::Sentence);
});

it('offers the heading strategy for Markdown only, and sentences and fixed sizes for the rest, the default first', function (): void {
    expect(ContentKind::Markdown->strategies())->toBe([SplitStrategy::Markdown, SplitStrategy::Recursive])
        ->and(ContentKind::Text->strategies())->toBe([SplitStrategy::Recursive, SplitStrategy::Sentence, SplitStrategy::Fixed])
        ->and(ContentKind::Transcript->strategies())->toBe([SplitStrategy::Sentence, SplitStrategy::Recursive, SplitStrategy::Fixed]);
});

it('uses the strategy set for a kind, and the default when it does not suit the kind', function (): void {
    config(['chatvia.ai.chunking.strategies' => ['markdown' => 'fixed', 'text' => 'sentence', 'transcript' => 'not-a-strategy']]);

    expect(ContentKind::Markdown->strategy())->toBe(SplitStrategy::Markdown)
        ->and(ContentKind::Text->strategy())->toBe(SplitStrategy::Sentence)
        ->and(ContentKind::Transcript->strategy())->toBe(SplitStrategy::Sentence);
});

it('builds the setup of a kind from config, with the unit default when no size is set', function (): void {
    $config = ChunkingConfigData::forKind(ContentKind::Text);

    expect($config->strategy)->toBe(SplitStrategy::Recursive)
        ->and($config->unit)->toBe(ChunkSizeUnit::Tokens)
        ->and($config->size)->toBe(400)
        ->and($config->overlap)->toBe(0)
        ->and($config->encoding)->toBe('cl100k_base')
        ->and($config->measure())->toBeInstanceOf(TokenMeasure::class);

    config(['chatvia.ai.chunking.unit' => 'characters', 'chatvia.ai.chunking.overlap' => 50, 'chatvia.ai.tokenizer' => 'not-a-tokenizer']);
    $config = ChunkingConfigData::forKind(ContentKind::Text);

    expect($config->unit)->toBe(ChunkSizeUnit::Characters)
        ->and($config->size)->toBe(1600)
        ->and($config->overlap)->toBe(50)
        ->and($config->encoding)->toBe('cl100k_base')
        ->and($config->measure())->toBeInstanceOf(CharacterMeasure::class);

    config(['chatvia.ai.chunking.size' => 900, 'chatvia.ai.chunking.unit' => 'not-a-unit']);

    expect(ChunkingConfigData::forKind(ContentKind::Text)->size)->toBe(900)
        ->and(ChunkingConfigData::forKind(ContentKind::Text)->unit)->toBe(ChunkSizeUnit::Tokens);
});

it('changes the fingerprint with every setting that shapes the chunks', function (): void {
    $base = ChunkingConfigData::forKind(ContentKind::Markdown)->fingerprint();

    expect(ChunkingConfigData::forKind(ContentKind::Markdown)->fingerprint())->toBe($base);

    foreach ([
        ['chatvia.ai.chunking.unit' => 'characters'],
        ['chatvia.ai.chunking.size' => 500],
        ['chatvia.ai.chunking.overlap' => 20],
        ['chatvia.ai.chunking.strategies.markdown' => 'recursive'],
        ['chatvia.ai.tokenizer' => 'o200k_base'],
    ] as $change) {
        $saved = array_map(config(...), array_keys($change));
        config($change);

        expect(ChunkingConfigData::forKind(ContentKind::Markdown)->fingerprint())->not->toBe($base);

        config(array_combine(array_keys($change), $saved));
    }
});

it('ignores the tokenizer in the fingerprint when chunks are measured in characters', function (): void {
    config(['chatvia.ai.chunking.unit' => 'characters']);
    $base = ChunkingConfigData::forKind(ContentKind::Markdown)->fingerprint();

    config(['chatvia.ai.tokenizer' => 'o200k_base']);

    expect(ChunkingConfigData::forKind(ContentKind::Markdown)->fingerprint())->toBe($base);
});
