<?php

declare(strict_types=1);

use Domain\Knowledge\Source\Qa\Services\QuestionNormalizer;

/**
 * The normalized form of a question, so a message matches a saved question
 * as a whole whatever its case, accents, diacritics, punctuation or spaces.
 */
it('normalizes case, accents, Arabic diacritics, punctuation and spaces', function (string $text, string $normalized): void {
    expect(QuestionNormalizer::normalize($text))->toBe($normalized);
})->with([
    'case and punctuation' => ['What are your OPENING hours?', 'what are your opening hours'],
    'accents' => ['Où est le café ?', 'ou est le cafe'],
    'Arabic diacritics and tatweel' => ['مَتَى تَفْتَحُــون؟', 'متى تفتحون'],
    'symbols and spaces' => ['  price   in €  /  $ ', 'price in'],
    'only punctuation' => ['?!', ''],
]);
