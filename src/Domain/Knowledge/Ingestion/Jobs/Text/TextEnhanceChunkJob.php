<?php

namespace Domain\Knowledge\Ingestion\Jobs\Text;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Data\OCR\PageData;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Ingestion\Services\ChunkEnhanceText;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Llm\Usage\AttributesTokenUsage;
use Domain\Llm\Usage\TracksTokenUsage;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

class TextEnhanceChunkJob implements AttributesTokenUsage, ShouldBeUnique, ShouldQueue
{
    use Queueable, TracksTokenUsage;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public bool $deleteWhenMissingModels = true;

    public int $timeout = 800;

    public function __construct(
        public Agent $agent,
        public SourceText|SourceFile $sourceText,
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

    /**
     * The source can be a SourceText or a SourceFile (media), whose ids
     * overlap, so the morph alias is part of the key.
     */
    public function uniqueId(): string
    {
        return 'agent:'.$this->agent->id
            .':sources:'.$this->sourceText->getMorphClass().':'.$this->sourceText->id
            .':page:'.$this->page->pageNumber;
    }
}
