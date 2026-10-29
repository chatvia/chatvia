<?php

use Domain\Knowledge\Ingestion\Services\Chunking\Splitting\TokenMeasure;

/**
 * Token windows: a token can hold part of a multi-byte character, so a
 * window only ends where a token starts a new character. Every window is
 * valid UTF-8, the windows cover the whole text, and they always move
 * forward.
 */
it('never cuts inside an Arabic letter or an emoji', function (string $text, int $size): void {
    $windows = new TokenMeasure('cl100k_base')->windows($text, $size, 0);

    expect(implode('', $windows))->toBe($text)
        ->and(array_filter($windows, fn (string $window): bool => ! mb_check_encoding($window, 'UTF-8')))->toBe([]);
})->with([
    'Arabic' => ['مرحبا بكم في مكتبتنا. نحن نقدم الكتب العربية والإنجليزية للجميع.'],
    'emoji' => ['Family 👨‍👩‍👧 and flags 🇸🇪🇩🇪 and a thumbs up 👍🏽 here.'],
])->with([
    'windows of 1 token' => 1,
    'windows of 3 tokens' => 3,
]);

it('repeats the overlap at the start of the next window, and always moves forward', function (): void {
    $measure = new TokenMeasure('cl100k_base');
    $text = 'one two three four five six seven eight nine ten';
    $windows = $measure->windows($text, 4, 2);

    expect($windows[0])->toBe('one two three four')
        ->and($windows[1])->toStartWith(' three four')
        ->and(end($windows))->toEndWith(' ten')
        ->and(count($measure->windows($text, 3, 3)))->toBeLessThanOrEqual(10);
});
