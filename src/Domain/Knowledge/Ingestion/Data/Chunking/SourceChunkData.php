<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Data\Chunking;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Spatie\LaravelData\Data;

/**
 * One page (or text chunk) of a file or text source to turn into a chunk, with
 * its neighbours for the enhancement step and the agent whose page shows the
 * progress.
 */
final class SourceChunkData extends Data
{
    public function __construct(
        public Agent $agent,
        public SourceFile|SourceText $source,
        public PageData $page,
        public ?PageData $previousPage = null,
        public ?PageData $nextPage = null,
    ) {}
}
