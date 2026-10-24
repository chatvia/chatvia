<?php

use Domain\Knowledge\Ingestion\Data\Chunking\ChunkingConfigData;
use Domain\Knowledge\Ingestion\Enums\ChunkSizeUnit;
use Domain\Knowledge\Ingestion\Enums\ContentKind;
use Domain\Knowledge\Ingestion\Enums\SplitStrategy;
use Domain\Knowledge\Ingestion\Services\Chunking\Splitting\CharacterMeasure;
use Domain\Knowledge\Ingestion\Services\Chunking\Splitting\TokenMeasure;

/**
 * The chunking setup of a kind of content reads the unit, size, overlap and
 * tokenizer from config (Settings → Chunking), with the unit's default size
 * when none is set. Its fingerprint changes with every setting that shapes
 * the chunks, so a page is trained again once they change.
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

it('keeps the fingerprint while the settings stay the same', function (): void {
    expect(ChunkingConfigData::forKind(ContentKind::Markdown)->fingerprint())
        ->toBe(ChunkingConfigData::forKind(ContentKind::Markdown)->fingerprint());
});

it('changes the fingerprint with every setting that shapes the chunks', function (array $change): void {
    $base = ChunkingConfigData::forKind(ContentKind::Markdown)->fingerprint();

    config($change);

    expect(ChunkingConfigData::forKind(ContentKind::Markdown)->fingerprint())->not->toBe($base);
})->with([
    'the unit' => [['chatvia.ai.chunking.unit' => 'characters']],
    'the size' => [['chatvia.ai.chunking.size' => 500]],
    'the overlap' => [['chatvia.ai.chunking.overlap' => 20]],
    'the strategy' => [['chatvia.ai.chunking.strategies.markdown' => 'recursive']],
    'the tokenizer' => [['chatvia.ai.tokenizer' => 'o200k_base']],
]);

it('ignores the tokenizer in the fingerprint when chunks are measured in characters', function (): void {
    config(['chatvia.ai.chunking.unit' => 'characters']);
    $base = ChunkingConfigData::forKind(ContentKind::Markdown)->fingerprint();

    config(['chatvia.ai.tokenizer' => 'o200k_base']);

    expect(ChunkingConfigData::forKind(ContentKind::Markdown)->fingerprint())->toBe($base);
});
