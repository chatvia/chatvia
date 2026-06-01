<?php

namespace Domain\Ingestion\Jobs\Document;

use Domain\Agents\Models\Agent;
use Domain\Ingestion\Actions\Sources\CreateSourceChunkAction;
use Domain\Ingestion\Data\OCR\PageData;
use Domain\Sources\Files\Events\FileIngestionProgressed;
use Domain\Sources\Files\Models\SourceFile;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class DocumentChunkIngestionJob implements ShouldQueue
{
    use Batchable, Queueable;

    public bool $deleteWhenMissingModels = true;

    public int $tries = 1;

    public int $backoff = 10;

    public function __construct(
        public Agent $agent,
        public SourceFile $sourceFile,
        public PageData $page,
        public ?PageData $previousPage = null,
        public ?PageData $nextPage = null,
    ) {}

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
            config('ai.chunker.enhance') === true,
            $this->agent,
            $this->sourceFile,
            $chunk,
            $this->page,
            $this->previousPage,
            $this->nextPage,
        );
    }
}
