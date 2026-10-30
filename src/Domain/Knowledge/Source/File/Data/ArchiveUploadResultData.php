<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\File\Data;

use Spatie\LaravelData\Data;
use Spatie\TypeScriptTransformer\Attributes\TypeScript;

/**
 * What uploading an archive did: how many files inside it were stored as
 * knowledge, their length together (pages, or minutes for recordings), and
 * the names of the entries that were left out, each with the reason, for the
 * uploader to show.
 */
#[TypeScript]
final class ArchiveUploadResultData extends Data
{
    /**
     * @param  list<string>  $skipped
     */
    public function __construct(
        public int $uploaded,
        public int $length,
        public array $skipped,
    ) {}
}
