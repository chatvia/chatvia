<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Data\Chunking;

use Domain\Knowledge\Ingestion\Enums\ChunkEnhancement;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Knowledge\Source\Web\Models\SourceWebsite;
use Spatie\LaravelData\Data;

/**
 * Pages of one source to store as chunks: how they are enhanced, the storage
 * folder for the images of document pages, and the splitter that cut them
 * (null when the parser or the token splitter made the pages).
 */
final class ChunkPagesData extends Data
{
    public function __construct(
        public SourceFile|SourceText|SourceWebsite $source,
        public PageGroupData $group,
        public ChunkEnhancement $enhancement,
        public ?string $imageDirectory = null,
        public ?string $chunkedUsingModel = null,
    ) {}
}
