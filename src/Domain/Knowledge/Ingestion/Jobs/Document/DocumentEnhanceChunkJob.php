<?php

namespace Domain\Knowledge\Ingestion\Jobs\Document;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Chunks\EnhanceChunkAction;
use Domain\Knowledge\Ingestion\Data\Chunking\EnhanceChunkData;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Services\KnowledgeStoragePath;
use Domain\Llm\Usage\AttributesTokenUsage;
use Domain\Llm\Usage\TracksTokenUsage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Enhances the chunk of one document page, including its images and tables
 * (saved under the agent's storage folder). See {@see EnhanceChunkAction}.
 */
class DocumentEnhanceChunkJob implements AttributesTokenUsage, ShouldQueue
{
    use Queueable, TracksTokenUsage;

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

    public function tokenWorkspaceId(): ?int
    {
        return $this->agent->user_id;
    }

    public function handle(EnhanceChunkAction $action): void
    {
        $action->execute(new EnhanceChunkData(
            chunk: $this->chunk,
            page: $this->page,
            previousPage: $this->previousPage,
            nextPage: $this->nextPage,
            imageDirectory: KnowledgeStoragePath::forAgent($this->agent),
        ));
    }

    public function failed(Throwable $e): void
    {
        report($e);
    }
}
