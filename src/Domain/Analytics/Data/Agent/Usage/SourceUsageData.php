<?php

declare(strict_types=1);

namespace Domain\Analytics\Data\Agent\Usage;

/**
 * One knowledge source an agent retrieved from, as recorded at retrieval time:
 * its kind (the morph alias, e.g. `source_file`), id, the name and MIME type it
 * had then (null on rows recorded before those were kept), how often it was
 * retrieved, and the last day it was.
 */
final readonly class SourceUsageData
{
    public function __construct(
        public string $kind,
        public int $sourceId,
        public ?string $name,
        public ?string $mimeType,
        public int $retrievals,
        public ?string $lastUsedOn,
    ) {}
}
