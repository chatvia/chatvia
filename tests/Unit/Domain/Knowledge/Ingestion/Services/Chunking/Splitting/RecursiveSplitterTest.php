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
 * up to the size, and falls back to fixed windows. It works the same in
 * tokens and in characters.
 */
function rsTextSeparators(): array
{
    return [PatternSeparator::paragraphs(), PatternSeparator::lines(), new SentenceSeparator, PatternSeparator::words()];
}

function rsText(): string
{
    return implode("\n\n", [
        'Our shop opens at nine. It closes at six. On Sundays it is closed.',
        'Returns are free within 30 days. Keep the receipt. Items must be unused.',
        "Shipping takes two days.\nExpress shipping takes one day.",
        'نحن نشحن إلى جميع الدول. الشحن مجاني للطلبات الكبيرة. 👨‍👩‍👧 مرحبا بالعائلات.',
    ]);
}

dataset('measures', [
    'tokens' => [fn (): ChunkMeasure => new TokenMeasure('cl100k_base'), 20],
    'characters' => [fn (): ChunkMeasure => new CharacterMeasure, 80],
]);

it('keeps every chunk within the size and loses no text', function (ChunkMeasure $measure, int $size): void {
    $chunks = new RecursiveSplitter($measure, $size)->split(rsText(), rsTextSeparators());

    expect(count($chunks))->toBeGreaterThan(1);

    foreach ($chunks as $chunk) {
        expect($measure->length($chunk))->toBeLessThanOrEqual($size)
            ->and(mb_check_encoding($chunk, 'UTF-8'))->toBeTrue();
    }

    expect(Str::replaceMatches('/\s+/u', '', implode('', $chunks)))->toBe(Str::replaceMatches('/\s+/u', '', rsText()));
})->with('measures');

it('keeps a text that fits as one chunk, and returns nothing for blank text', function (): void {
    $splitter = new RecursiveSplitter(new CharacterMeasure, 100);

    expect($splitter->split('  Short text.  ', rsTextSeparators()))->toBe(['Short text.'])
        ->and($splitter->split(" \n\n ", rsTextSeparators()))->toBe([])
        ->and($splitter->split('', rsTextSeparators()))->toBe([]);
});

it('splits at paragraphs first, and packs paragraphs that fit together', function (): void {
    $text = "First paragraph.\n\nSecond one.\n\nA third paragraph that is long.";

    expect(new RecursiveSplitter(new CharacterMeasure, 35)->split($text, rsTextSeparators()))->toBe([
        "First paragraph.\n\nSecond one.",
        'A third paragraph that is long.',
    ]);
});

it('splits a long paragraph between sentences, then between words', function (): void {
    $splitter = new RecursiveSplitter(new CharacterMeasure, 30);

    expect($splitter->split('It rains today. Bring a coat. The wind is strong.', rsTextSeparators()))->toBe([
        'It rains today. Bring a coat.',
        'The wind is strong.',
    ])->and($splitter->split('one two three four five six seven eight nine ten eleven', rsTextSeparators()))->toBe([
        'one two three four five six',
        'seven eight nine ten eleven',
    ]);
});

it('falls back to fixed windows for a run with no place to split', function (ChunkMeasure $measure, int $size): void {
    $run = Str::repeat('abcdefghij', 30);
    $chunks = new RecursiveSplitter($measure, $size)->split($run, rsTextSeparators());

    expect(implode('', $chunks))->toBe($run);

    foreach ($chunks as $chunk) {
        expect($measure->length($chunk))->toBeLessThanOrEqual($size);
    }
})->with('measures');

it('repeats whole pieces from the end of a chunk at the start of the next', function (): void {
    $text = 'one two three four five six seven eight nine ten';
    $chunks = new RecursiveSplitter(new CharacterMeasure, 20, 10)->split($text, [PatternSeparator::words()]);

    expect($chunks)->toBe([
        'one two three four',
        'four five six seven',
        'six seven eight',
        'eight nine ten',
    ]);
});

it('packs whole sentences, in Arabic and English', function (): void {
    $text = 'مرحبا بكم. نحن نشحن إلى جميع الدول. Welcome. We ship everywhere.';
    $chunks = new RecursiveSplitter(new CharacterMeasure, 40)->split($text, [new SentenceSeparator, PatternSeparator::words()]);

    expect($chunks)->toBe([
        'مرحبا بكم. نحن نشحن إلى جميع الدول.',
        'Welcome. We ship everywhere.',
    ]);
});

it('cuts fixed windows when it has no separators', function (): void {
    expect(new RecursiveSplitter(new CharacterMeasure, 5)->split('abcdefghijkl', []))->toBe(['abcde', 'fghij', 'kl']);
});

it('packs measured pieces with the overlap', function (): void {
    $splitter = new RecursiveSplitter(new CharacterMeasure, 10, 4);

    expect($splitter->pack([['aaaa', 4], ['bbbb', 4], ['cccc', 4], ['dddddddddddd', 12], ['ee', 2]]))->toBe([
        'aaaabbbb',
        'bbbbcccc',
        'dddddddddddd',
        'ee',
    ])->and($splitter->pack([]))->toBe([]);
});
