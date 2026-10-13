<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Services\Chunking\Splitting;

use Domain\Knowledge\Ingestion\Contracts\ChunkMeasure;

/**
 * Measures chunks in characters as a reader sees them (grapheme clusters),
 * so a letter with its diacritics, or an emoji made of several code points,
 * counts once and is never cut in two.
 */
final readonly class CharacterMeasure implements ChunkMeasure
{
    public function length(string $text): int
    {
        $length = grapheme_strlen($text);

        return is_int($length) ? $length : mb_strlen($text);
    }

    public function windows(string $text, int $size, int $overlap): array
    {
        if ($text === '') {
            return [];
        }

        $characters = grapheme_str_split($text);

        if ($characters === false) {
            $characters = mb_str_split($text);
        }

        $count = count($characters);
        $size = max(1, $size);
        $step = $size - max(0, min($overlap, $size - 1));
        $windows = [];

        for ($start = 0; ; $start += $step) {
            $windows[] = implode('', array_slice($characters, $start, $size));

            if ($start + $size >= $count) {
                break;
            }
        }

        return $windows;
    }
}
