<?php

use Domain\Knowledge\Ingestion\Services\Chunking\Splitting\TokenMeasure;
use Domain\Llm\Services\TikToken;

/**
 * Token windows: a token can hold part of a multi-byte character, so a
 * window only ends where a token starts a new character. Every window is
 * valid UTF-8, the windows cover the whole text, and they always move
 * forward.
 */
it('measures text in tokens of its encoding', function (): void {
    $text = 'Tokenizers split words like antidisestablishmentarianism differently.';

    expect(new TokenMeasure('cl100k_base')->length($text))->toBe(TikToken::countTokens($text, 'cl100k_base'))
        ->and(new TokenMeasure('r50k_base')->length($text))->toBe(TikToken::countTokens($text, 'r50k_base'))
        ->and(new TokenMeasure('cl100k_base')->length($text))->not->toBe(new TokenMeasure('r50k_base')->length($text));
});

it('cuts windows of the size that join back into the text', function (): void {
    $measure = new TokenMeasure('cl100k_base');
    $text = str_repeat('The quick brown fox jumps over the lazy dog. ', 20);
    $windows = $measure->windows($text, 16, 0);

    expect(implode('', $windows))->toBe($text);

    foreach ($windows as $window) {
        expect($measure->length($window))->toBeLessThanOrEqual(16);
    }
});

it('never cuts inside an Arabic letter or an emoji', function (string $text): void {
    $measure = new TokenMeasure('cl100k_base');

    foreach ([1, 2, 3, 7] as $size) {
        $windows = $measure->windows($text, $size, 0);

        expect(implode('', $windows))->toBe($text);

        foreach ($windows as $window) {
            expect(mb_check_encoding($window, 'UTF-8'))->toBeTrue();
        }
    }
})->with([
    'Arabic' => ['مرحبا بكم في مكتبتنا. نحن نقدم الكتب العربية والإنجليزية للجميع.'],
    'emoji' => ['Family 👨‍👩‍👧 and flags 🇸🇪🇩🇪 and a thumbs up 👍🏽 here.'],
    'Chinese' => ['我们的商店每天营业，欢迎光临。'],
]);

it('repeats the overlap at the start of the next window, and always moves forward', function (): void {
    $measure = new TokenMeasure('cl100k_base');
    $text = 'one two three four five six seven eight nine ten';
    $windows = $measure->windows($text, 4, 2);

    expect($windows[0])->toBe('one two three four')
        ->and($windows[1])->toStartWith(' three four')
        ->and(end($windows))->toEndWith(' ten');

    // An overlap as large as the size still moves on by at least one token.
    expect(count($measure->windows($text, 3, 3)))->toBeLessThanOrEqual(10);
});

it('returns no windows for empty text', function (): void {
    expect(new TokenMeasure('cl100k_base')->windows('', 10, 0))->toBe([]);
});
