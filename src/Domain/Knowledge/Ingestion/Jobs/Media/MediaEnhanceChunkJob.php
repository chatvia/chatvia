<?php

namespace Domain\Knowledge\Ingestion\Jobs\Media;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Data\OCR\PageData;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Ingestion\Services\ChunkEnhanceText;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Llm\Usage\AttributesTokenUsage;
use Domain\Llm\Usage\TracksTokenUsage;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class MediaEnhanceChunkJob implements ShouldBeUnique, ShouldQueue, AttributesTokenUsage
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, TracksTokenUsage;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public bool $deleteWhenMissingModels = true;

    public int $timeout = 800;

    public function __construct(
        public Agent $agent,
        public SourceFile $sourceFile,
        private readonly Chunk $chunk,
        private readonly PageData $page,
        public ?PageData $previousPage = null,
        public ?PageData $nextPage = null,
    ) {}

    public function tokenWorkspaceId(): ?int
    {
        return $this->agent->user_id;
    }

    public function handle(ChunkEnhanceText $enhanceText): void
    {
        $enhanceText->enhanceText(
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

    public function uniqueId(): string
    {
        return 'agents:'.$this->agent->id.':sources:file:'.$this->sourceFile->id.':checksum:'.$this->page->checksum;
    }
}
