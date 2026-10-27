<?php

declare(strict_types=1);

namespace Domain\Knowledge\Retrieval\Data;

/**
 * Points at one knowledge source a turn retrieved: its morph alias (as
 * chunks.chunkable_type stores it) and its id. The retrieval stats job carries
 * these references only, never the chunk text.
 */
final readonly class SourceRefData
{
    public function __construct(
        public string $type,
        public int $id,
    ) {}
}
