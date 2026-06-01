<?php

namespace Domain\Ingestion\Jobs\Document;

use Domain\Agents\Models\Agent;
use Domain\Ingestion\Actions\Documents\EnhanceDocumentChunkAction;
use Domain\Ingestion\Data\OCR\PageData;
use Domain\Ingestion\Models\Chunk;
use Domain\Sources\Files\Models\SourceFile;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class DocumentEnhanceChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public bool $deleteWhenMissingModels = true;

    public int $timeout = 800;

    public function __construct(
        public Agent $agent,
        public SourceFile $sourceFile,
        public Chunk $chunk,
        public PageData $page,
        public ?PageData $previousPage = null,
        public ?PageData $nextPage = null,
    ) {}

    public function handle(EnhanceDocumentChunkAction $action): void
    {
        $action->execute(
            $this->agent,
            $this->sourceFile,
            $this->chunk,
            $this->page,
            $this->previousPage,
            $this->nextPage,
        );
    }

    public function failed(Throwable $e): void
    {
        report($e);
    }
}
