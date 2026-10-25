<?php

use Domain\Llm\Services\SpeechTextParts;
use Illuminate\Support\Str;

/**
 * A text to read aloud, in parts a speech service takes in one call: cut
 * between sentences, and a sentence too long for one part between words.
 */
it('keeps a short text whole', function (): void {
    expect(app(SpeechTextParts::class)->split('One. Two.', 100))->toBe(['One. Two.'])
        ->and(app(SpeechTextParts::class)->split('  ', 100))->toBe([]);
});

it('cuts a long text between sentences', function (): void {
    expect(app(SpeechTextParts::class)->split('First one here. Second one here. Third one here.', 33))
        ->toBe(['First one here. Second one here.', 'Third one here.']);
});

it('cuts a long sentence between words, keeping every word', function (): void {
    $parts = app(SpeechTextParts::class)->split('alpha beta gamma delta epsilon', 12);

    expect(collect($parts)->every(fn (string $part): bool => Str::length($part) <= 12))->toBeTrue()
        ->and(implode(' ', $parts))->toBe('alpha beta gamma delta epsilon');
});
