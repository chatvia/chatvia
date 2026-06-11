<?php

namespace Domain\Knowledge\Ingestion\Jobs\Document;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Sources\CreateSourceChunkAction;
use Domain\Knowledge\Ingestion\Data\OCR\PageData;
use Domain\Knowledge\Source\File\Events\FileIngestionProgressed;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Llm\Usage\AttributesTokenUsage;
use Domain\Llm\Usage\TracksTokenUsage;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DocumentChunkIngestionJob implements AttributesTokenUsage, ShouldQueue
{
    use Batchable, Queueable, TracksTokenUsage;

    public bool $deleteWhenMissingModels = true;

    // With allowFailures() on the batch, retries are the only path that
    // recovers a page from a transient embed-API failure — match the
    // sibling Text/Media chunk jobs.
    public int $tries = 2;

    public int $backoff = 10;

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

    public function handle(CreateSourceChunkAction $createChunk): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        $chunk = $createChunk->execute($this->agent, $this->sourceFile, $this->page);

        if (! $chunk) {
            return;
        }

        $this->sourceFile->refresh();

        FileIngestionProgressed::dispatch($this->sourceFile, $this->agent->external_id);

        DocumentEnhanceChunkJob::dispatchIf(
            config('chatvia.ai.chunker.enhance') === true,
            $this->agent,
            $this->sourceFile,
            $chunk,
            $this->page,
            $this->previousPage,
            $this->nextPage,
        );
    }
}
