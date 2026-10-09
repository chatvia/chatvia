<?php

namespace Domain\Knowledge\Ingestion\Jobs\Media;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Chunks\IngestSourceChunkAction;
use Domain\Knowledge\Ingestion\Data\Chunking\SourceChunkData;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Llm\Usage\AttributesTokenUsage;
use Domain\Llm\Usage\TracksTokenUsage;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Turns one part of an audio or video transcript into a chunk. Runs inside the
 * file's ingestion batch; see {@see IngestSourceChunkAction}.
 */
class MediaChunkIngestionJob implements AttributesTokenUsage, ShouldBeUnique, ShouldQueue
{
    use Batchable, Queueable, TracksTokenUsage;

    public bool $deleteWhenMissingModels = true;

    public int $tries = 2;

    public function __construct(
        public Agent $agent,
        public SourceFile $sourceFile,
        public PageData $page,
        public ?PageData $previousPage = null,
        public ?PageData $nextPage = null,
    ) {}

    public function tokenWorkspaceId(): ?int
    {
        return $this->agent->user_id;
    }

    public function handle(IngestSourceChunkAction $action): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $action->execute(new SourceChunkData(
            agent: $this->agent,
            source: $this->sourceFile,
            page: $this->page,
            previousPage: $this->previousPage,
            nextPage: $this->nextPage,
        ));
    }

    public function uniqueId(): string
    {
        return 'agent:'.$this->agent->id.':sources:text:'.$this->sourceFile->id.':page:'.$this->page->pageNumber;
    }
}
