<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\Qa\Services;

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
        $text = mb_strtolower(is_string($decomposed) ? $decomposed : $text);

        // Combining marks (accents, Arabic diacritics) and the Arabic tatweel.
        $text = (string) preg_replace('/[\p{Mn}\x{0640}]+/u', '', $text);
        $text = (string) preg_replace('/[\p{P}\p{S}]+/u', ' ', $text);

        return trim((string) preg_replace('/\s+/u', ' ', $text));
    }
}
