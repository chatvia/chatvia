<?php

namespace Domain\Knowledge\Ingestion\Jobs\Text;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Chunks\EnhanceChunkAction;
use Domain\Knowledge\Ingestion\Data\Chunking\EnhanceChunkData;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Llm\Usage\AttributesTokenUsage;
use Domain\Llm\Usage\TracksTokenUsage;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Enhances the chunk of a text source or a media transcript part, from its
 * text only. See {@see EnhanceChunkAction}.
 *
 * The property keeps the name `sourceText` although it may hold a media
 * SourceFile: jobs already on the queue are unserialized by property name.
 */
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

    public function handle(EnhanceChunkAction $action): void
    {
        $action->execute(new EnhanceChunkData(
            chunk: $this->chunk,
            page: $this->page,
            previousPage: $this->previousPage,
            nextPage: $this->nextPage,
        ));
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
