<?php

namespace Domain\Analytics\Data\Recording;

use Spatie\LaravelData\Data;

/**
 * One knowledge source used to answer a message: its morph alias and id, plus
 * the name and mime type captured at retrieval time so the rollup still has a
 * label after the source is deleted.
 */
class RetrievedSourceData extends Data
{
    public function __construct(
        public string $type,
        public int $id,
        public ?string $name = null,
        public ?string $mime = null,
    ) {}
}
