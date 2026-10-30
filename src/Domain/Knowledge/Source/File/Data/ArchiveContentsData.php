<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\File\Data;

use Spatie\LaravelData\Data;

/**
 * What an archive extractor took out of an uploaded archive: the files of a
 * knowledge format, and the names of the entries it left out (another
 * format, too large, or an archive it could not open). Folders and the junk
 * some tools add (`__MACOSX`, dot files) are neither.
 */
final class ArchiveContentsData extends Data
{
    /**
     * @param  list<ExtractedArchiveFileData>  $files
     * @param  list<string>  $skipped
     */
    public function __construct(
        public array $files,
        public array $skipped,
    ) {}
}
