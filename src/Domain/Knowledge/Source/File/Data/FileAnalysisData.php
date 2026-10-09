<?php

declare(strict_types=1);

namespace Domain\Knowledge\Source\File\Data;

use Spatie\LaravelData\Data;

/**
 * The size of a file as the quota counts it: pages for documents and images,
 * minutes for audio and video. `metadata` holds what pdfinfo reported (title,
 * page size, …) when the file is a PDF.
 */
class FileAnalysisData extends Data
{
    /**
     * @param  array<string, mixed>  $metadata
     */
    public function __construct(
        public int $length,
        public array $metadata = [],
    ) {}
}
