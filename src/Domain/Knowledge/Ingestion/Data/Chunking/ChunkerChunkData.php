<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Data\Chunking;

use Spatie\LaravelData\Data;

/**
 * One chunk the chunker returned: its text and its size in tokens (counted
 * with the tokenizer of the request).
 */
final class ChunkerChunkData extends Data
{
    public function __construct(
        public string $text,
        public int $tokenCount,
    ) {}
}
