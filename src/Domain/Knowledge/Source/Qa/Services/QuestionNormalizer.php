<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\Qa\Services;

use Illuminate\Support\Str;
use Normalizer;

/**
 * The form of a question that two ways of typing it share: lower case,
 * without accents or Arabic diacritics and tatweel, without punctuation or
 * symbols, and with single spaces. "What are your opening hours?" and
 * "what are your OPENING hours" become the same text.
 */
final class QuestionNormalizer
{
    public static function normalize(string $text): string
    {
        $decomposed = Normalizer::normalize($text, Normalizer::FORM_KD);
        $text = Str::lower($decomposed === false ? $text : $decomposed);

        // Combining marks (accents, Arabic diacritics) and the Arabic tatweel.
        return Str::of($text)
            ->replaceMatches('/[\p{Mn}\x{0640}]+/u', '')
            ->replaceMatches('/[\p{P}\p{S}]+/u', ' ')
            ->replaceMatches('/\s+/u', ' ')
            ->trim()
            ->toString();
    }
}
