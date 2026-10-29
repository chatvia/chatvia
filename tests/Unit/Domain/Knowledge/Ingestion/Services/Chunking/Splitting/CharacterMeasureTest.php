<?php

use Domain\Knowledge\Ingestion\Services\Chunking\Splitting\CharacterMeasure;

/**
 * Character windows count characters as a reader sees them: a letter with
 * its diacritics, or an emoji made of several code points, is one character
 * and is never cut.
 */
it('cuts windows of exactly the size, with the overlap repeated', function (): void {
    $measure = new CharacterMeasure;

    expect($measure->windows('abcdefghij', 4, 0))->toBe(['abcd', 'efgh', 'ij'])
        ->and($measure->windows('abcdefghij', 4, 2))->toBe(['abcd', 'cdef', 'efgh', 'ghij'])
        ->and($measure->windows('abc', 10, 0))->toBe(['abc'])
        ->and($measure->windows('', 4, 0))->toBe([]);
});

it('never cuts a letter from its diacritics or an emoji in two', function (): void {
    $text = 'مَرْحَبًا 👨‍👩‍👧 café';
    $windows = (new CharacterMeasure)->windows($text, 3, 0);

    expect(implode('', $windows))->toBe($text)
        ->and($windows)->toContain('👨‍👩‍👧 c');

    foreach ($windows as $window) {
        expect(grapheme_strlen($window))->toBeLessThanOrEqual(3);
    }
});
