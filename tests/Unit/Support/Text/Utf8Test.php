<?php

declare(strict_types=1);

use Support\Text\Utf8;

/**
 * Utf8::truncateBytes() cuts text to a byte limit (a column or a log line)
 * without splitting a character that takes several bytes.
 */
it('never splits a multibyte character and leaves short text unchanged', function (string $text, int $max, string $expected): void {
    $cut = Utf8::truncateBytes($text, $max);

    expect($cut)->toBe($expected)
        ->and(mb_check_encoding($cut, 'UTF-8'))->toBeTrue()
        ->and(strlen($cut))->toBeLessThanOrEqual($max);
})->with([
    'arabic, cut mid-letter' => ['مرحبا', 5, 'مر'],
    'emoji, cut mid-glyph' => ['a😀b', 3, 'a'],
    'zero' => ['abc', 0, ''],
    'short text' => ['مرحبا', 10, 'مرحبا'],
]);
