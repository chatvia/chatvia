<?php

declare(strict_types=1);

use Domain\Llm\Data\Video\VideoPromptData;

/**
 * Each video service makes only some lengths, so the length asked for is
 * moved to the nearest one a model makes, or kept within its range.
 */
it('picks the nearest length a model makes, the longer one on a tie', function (int $asked, int $made): void {
    expect(new VideoPromptData('A wave', seconds: $asked)->secondsAmong([4, 6, 8]))->toBe($made);
})->with([
    'exact' => [6, 6],
    'between, nearer the lower' => [4, 4],
    'tie' => [5, 6],
    'too long' => [15, 8],
    'too short' => [2, 4],
]);

it('keeps the length within a model\'s range', function (int $asked, int $made): void {
    expect(new VideoPromptData('A wave', seconds: $asked)->secondsBetween(3, 10))->toBe($made);
})->with([
    'inside' => [8, 8],
    'too short' => [2, 3],
    'too long' => [15, 10],
]);
