<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Contracts;

/**
 * How the size of a chunk is measured (tokens or characters, see
 * Domain\Knowledge\Ingestion\Enums\ChunkSizeUnit), and how a text is cut into
 * windows of a fixed size in that unit when it has no better place to split.
 */
interface ChunkMeasure
{
    /** The length of the text in this unit. */
    public function length(string $text): int;

    /**
     * The text cut into windows of at most $size units (one unit over only
     * when a single unit is larger), each starting $overlap units before the
     * end of the previous one. Never cuts inside a character.
     *
     * @return list<string> lossless pieces of the text, in order
     */
    public function windows(string $text, int $size, int $overlap): array;
}
