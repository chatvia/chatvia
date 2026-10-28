<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Services\Chunking\Splitting;

use Domain\Knowledge\Ingestion\Contracts\Separator;
use IntlBreakIterator;

/**
 * Splits a text between sentences with the Unicode sentence rules of ICU, so
 * it works for every language Chatvia serves, not only English. Common
 * English abbreviations ("Mr.", "p.m.") do not end a sentence.
 */
final readonly class SentenceSeparator implements Separator
{
    /** The ICU locale: the default rules, plus the standard abbreviations. */
    private const string LOCALE = 'en@ss=standard';

    public function split(string $text): array
    {
        if ($text === '') {
            return [];
        }

        $iterator = IntlBreakIterator::createSentenceInstance(self::LOCALE);

        if (! $iterator instanceof IntlBreakIterator || ! $iterator->setText($text)) {
            return [$text];
        }

        $pieces = [];
        $start = 0;

        // The boundaries are byte offsets into the UTF-8 text.
        foreach ($iterator as $boundary) {
            if ($boundary > $start) {
                $pieces[] = substr($text, $start, $boundary - $start);
                $start = $boundary;
            }
        }

        if ($start < strlen($text)) {
            $pieces[] = substr($text, $start);
        }

        return $pieces;
    }
}
