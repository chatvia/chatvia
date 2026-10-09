<?php

declare(strict_types=1);

namespace Domain\Analytics\Data\Agent\Usage;

/**
 * One of an agent's knowledge files and how often answers retrieved it.
 * `classification` is the document type the analyzer gave the file, if any.
 */
final readonly class FileUsageData
{
    public function __construct(
        public string $filename,
        public ?string $classification,
        public int $retrievals,
    ) {}
}
