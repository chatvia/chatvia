<?php

use Domain\Knowledge\Ingestion\Contracts\ChunkMeasure;
use Domain\Knowledge\Ingestion\Services\Chunking\Splitting\CharacterMeasure;
use Domain\Knowledge\Ingestion\Services\Chunking\Splitting\PatternSeparator;
use Domain\Knowledge\Ingestion\Services\Chunking\Splitting\RecursiveSplitter;
use Domain\Knowledge\Ingestion\Services\Chunking\Splitting\SentenceSeparator;
use Domain\Knowledge\Ingestion\Services\Chunking\Splitting\TokenMeasure;
use Illuminate\Support\Str;

/**
 * The recursive splitter cuts at the coarsest place that makes the pieces
 * fit (paragraphs, then lines, sentences, words), packs neighbouring pieces
 * up to the size, and falls back to fixed windows. Every chunk fits and no
 * text is lost, in tokens and in characters, with or without a smallest
 * chunk; a last chunk under the minimum is evened out with the one before.
 */
function rsText(): string
{
    return implode("\n\n", [
        'Our shop opens at nine. It closes at six. On Sundays it is closed.',
        'Returns are free within 30 days. Keep the receipt. Items must be unused.',
        "Shipping takes two days.\nExpress shipping takes one day.",
        'نحن نشحن إلى جميع الدول. الشحن مجاني للطلبات الكبيرة. 👨‍👩‍👧 مرحبا بالعائلات.',
    ]);
}

it('keeps every chunk within the size and loses no text, with or without a minimum', function (ChunkMeasure $measure, int $size, int $minimum): void {
    $separators = [PatternSeparator::paragraphs(), PatternSeparator::lines(), new SentenceSeparator, PatternSeparator::words()];

    $chunks = new RecursiveSplitter($measure, $size, 0, $minimum)->split(rsText(), $separators);

    expect(count($chunks))->toBeGreaterThan(1)
        ->and(collect($chunks)->every(fn (string $chunk): bool => $measure->length($chunk) <= $size && mb_check_encoding($chunk, 'UTF-8')))->toBeTrue()
        ->and(Str::replaceMatches('/\s+/u', '', implode('', $chunks)))->toBe(Str::replaceMatches('/\s+/u', '', rsText()));
})->with([
    'tokens' => [fn (): ChunkMeasure => new TokenMeasure('cl100k_base'), 20, 0],
    'characters' => [fn (): ChunkMeasure => new CharacterMeasure, 80, 0],
    'tokens with a minimum' => [fn (): ChunkMeasure => new TokenMeasure('cl100k_base'), 20, 10],
    'characters with a minimum' => [fn (): ChunkMeasure => new CharacterMeasure, 80, 40],
]);

it('evens out a last chunk under the minimum with the one before', function (): void {
    $pieces = [['aaaaaaaa', 8], ['bbbbbbbb', 8], ['cc', 2]];

    expect(new RecursiveSplitter(new CharacterMeasure, 16)->pack($pieces))->toBe(['aaaaaaaabbbbbbbb', 'cc'])
        ->and(new RecursiveSplitter(new CharacterMeasure, 16, 0, 6)->pack($pieces))->toBe(['aaaaaaaa', 'bbbbbbbbcc']);
});
