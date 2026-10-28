<?php

use Domain\Conversation\Services\Voice\SpokenYes;

/**
 * The server, not the model, decides whether the visitor's spoken answer
 * was a yes: a plain yes in any dashboard language is one, a refusal
 * anywhere in the answer is not, and an answer in no known form is not.
 */
it('tells a spoken yes from everything else', function (?string $words, bool $yes): void {
    expect(SpokenYes::in($words))->toBe($yes);
})->with([
    'yes' => ['Yes, send it.', true],
    'okay' => ['Okay!', true],
    'yes but not' => ["Yes, but don't send it yet.", false],
    'no' => ['No.', false],
    'other words' => ['What does it cost?', false],
    'nothing' => ['', false],
    'null' => [null, false],
    'german' => ['Ja, bitte.', true],
    'arabic' => ['نعم، أرسلها.', true],
    'japanese' => ['はい、お願いします。', true],
    'japanese no' => ['いいえ、やめてください。', false],
    'chinese' => ['好的。', true],
    'chinese no' => ['不要。', false],
]);
