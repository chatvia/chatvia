<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Services\Chunking\Splitting;

use Domain\Knowledge\Ingestion\Contracts\ChunkMeasure;
use Domain\Llm\Services\TikToken;

/**
 * Measures chunks in tokens of a tiktoken encoding. A token can hold part of
 * a multi-byte character (an Arabic letter, an emoji), so windows end only
 * where a token starts a new character: every window is valid UTF-8.
 */
final readonly class TokenMeasure implements ChunkMeasure
{
    public function __construct(
        private string $encoding,
    ) {}

    public function length(string $text): int
    {
        return TikToken::countTokens($text, $this->encoding);
    }

    public function windows(string $text, int $size, int $overlap): array
    {
        $tokens = TikToken::tokenBytes($text, $this->encoding);
        $count = count($tokens);
        $size = max(1, $size);
        $overlap = max(0, min($overlap, $size - 1));

        // Byte offset where each token starts; the last entry is the end of the text.
        $offsets = [0];

        foreach ($tokens as $index => $bytes) {
            $offsets[$index + 1] = $offsets[$index] + strlen($bytes);
        }

        // A cut before token $index is clean when it does not fall inside a character.
        $clean = static fn (int $index): bool => $index === 0 || $index >= $count || (ord($text[$offsets[$index]]) & 0xC0) !== 0x80;

        $windows = [];
        $start = 0;

        while ($start < $count) {
            $end = min($start + $size, $count);

            while ($end > $start && ! $clean($end)) {
                $end--;
            }

            // No clean cut inside the window: end at the first one after it.
            if ($end === $start) {
                $end = min($start + $size, $count);

                while (! $clean($end)) {
                    $end++;
                }
            }

            $windows[] = substr($text, $offsets[$start], $offsets[$end] - $offsets[$start]);

            if ($end >= $count) {
                break;
            }

            $next = max($end - $overlap, $start + 1);

            while ($next < $end && ! $clean($next)) {
                $next++;
            }

            $start = $next;
        }

        return $windows;
    }
}
