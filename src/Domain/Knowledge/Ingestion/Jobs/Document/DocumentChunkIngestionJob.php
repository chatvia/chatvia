<?php

namespace Domain\Knowledge\Ingestion\Jobs\Document;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Chunks\IngestSourceChunkAction;
use Domain\Knowledge\Ingestion\Data\Chunking\SourceChunkData;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Llm\Usage\AttributesTokenUsage;
use Domain\Llm\Usage\TracksTokenUsage;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;

/**
 * Turns one page of a document or text file into a chunk. Runs inside the
 * file's ingestion batch; see {@see IngestSourceChunkAction}.
 */
class DocumentChunkIngestionJob implements AttributesTokenUsage, ShouldQueue
{
    use Batchable, Queueable, TracksTokenUsage;

    public bool $deleteWhenMissingModels = true;

    public int $tries = 2;

    public int $backoff = 10;

    public function __construct(
        public Agent $agent,
        public SourceFile $sourceFile,
        public PageData $page,
        public ?PageData $previousPage = null,
        public ?PageData $nextPage = null,
    ) {}

    /**
     * One job per page, each knowing its neighbours (context for enhancement).
     *
     * @param  Collection<int, PageData>  $pages
     * @return Collection<int, self>
     */
    public static function forPages(Agent $agent, SourceFile $sourceFile, Collection $pages): Collection
    {
        $pages = $pages->values();

        return $pages->map(fn (PageData $page, int $index): self => new self(
            $agent,
            $sourceFile,
            $page,
            $pages->get($index - 1),
            $pages->get($index + 1),
        ));
    }

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
}
