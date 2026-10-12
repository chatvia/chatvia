<?php

declare(strict_types=1);

namespace Domain\Knowledge\Ingestion\Data\Chunking;

use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Spatie\LaravelData\Data;

/**
 * Input for enhancing one page before it is stored as a chunk: the page and
 * its neighbours (context for the summary). `imageDirectory` is set for
 * document pages only: the page's images and tables are then described too,
 * and the images are saved in that storage folder.
 */
final class EnhanceChunkData extends Data
{
    public function __construct(
        public PageData $page,
        public ?PageData $previousPage = null,
        public ?PageData $nextPage = null,
        public ?string $imageDirectory = null,
    ) {}
}
