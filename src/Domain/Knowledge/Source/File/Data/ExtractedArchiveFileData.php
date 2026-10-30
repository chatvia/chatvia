<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\File\Data;

use Spatie\LaravelData\Data;

/**
 * One file taken out of an uploaded archive: where its bytes are on this
 * server, and its name inside the archive (folders included, nested archives
 * as folders), which becomes the source file's name.
 */
final class ExtractedArchiveFileData extends Data
{
    public function __construct(
        public string $path,
        public string $name,
    ) {}
}
