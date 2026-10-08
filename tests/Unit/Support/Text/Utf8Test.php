<?php

use Support\Text\Utf8;

it('returns short text unchanged', function (): void {
    expect(Utf8::truncateBytes('hello', 10))->toBe('hello')
        ->and(Utf8::truncateBytes('مرحبا', 10))->toBe('مرحبا');
});

it('never splits a multibyte character', function (string $text, int $max, string $expected): void {
    $cut = Utf8::truncateBytes($text, $max);

    expect($cut)->toBe($expected)
        ->and(mb_check_encoding($cut, 'UTF-8'))->toBeTrue()
        ->and(strlen($cut))->toBeLessThanOrEqual($max);
})->with([
    'arabic, cut mid-letter' => ['مرحبا', 5, 'مر'],
    'emoji, cut mid-glyph' => ['a😀b', 3, 'a'],
    'ascii' => ['abcdef', 4, 'abcd'],
    'zero' => ['abc', 0, ''],
]);
