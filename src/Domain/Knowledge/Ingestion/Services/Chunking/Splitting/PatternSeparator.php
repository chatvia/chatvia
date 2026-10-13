<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Services\Chunking\Splitting;

use Domain\Knowledge\Ingestion\Contracts\Separator;

/**
 * Splits a text after each match of a pattern (blank lines, line breaks,
 * spaces), so the whitespace stays at the end of the piece before it and the
 * pieces join back into the exact text.
 */
final readonly class PatternSeparator implements Separator
{
    public function __construct(
        private string $pattern,
    ) {}

    /** Between paragraphs: after one or more blank lines. */
    public static function paragraphs(): self
    {
        return new self('/\n(?:[ \t]*\n)+/');
    }

    /** Between lines. */
    public static function lines(): self
    {
        return new self('/\n+/');
    }

    /** Between words. */
    public static function words(): self
    {
        return new self('/\s+/u');
    }

    public function split(string $text): array
    {
        if (preg_match_all($this->pattern, $text, $matches, PREG_OFFSET_CAPTURE) === false) {
            return $text === '' ? [] : [$text];
        }

        $length = strlen($text);
        $pieces = [];
        $start = 0;

        foreach ($matches[0] as [$match, $offset]) {
            $end = $offset + strlen($match);

            if ($end > $start && $end < $length) {
                $pieces[] = substr($text, $start, $end - $start);
                $start = $end;
            }
        }

        if ($start < $length) {
            $pieces[] = substr($text, $start);
        }

        return $pieces;
    }
}
