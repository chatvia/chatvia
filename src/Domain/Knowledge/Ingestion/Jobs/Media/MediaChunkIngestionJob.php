<?php

namespace Domain\Knowledge\Ingestion\Jobs\Media;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Sources\CreateSourceChunkAction;
use Domain\Knowledge\Ingestion\Data\OCR\PageData;
use Domain\Knowledge\Ingestion\Jobs\Text\TextEnhanceChunkJob;
use Domain\Knowledge\Source\File\Events\FileIngestionProgressed;
use Domain\Knowledge\Source\File\Models\SourceFile;
use Domain\Llm\Usage\AttributesTokenUsage;
use Domain\Llm\Usage\TracksTokenUsage;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

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

        TextEnhanceChunkJob::dispatchIf(
            config('chatvia.ai.chunker.enhance') === true,
            $this->agent,
            $this->sourceFile,
            $chunk,
            $this->page,
            $this->previousPage,
            $this->nextPage,
        );
    }

    public function uniqueId(): string
    {
        return 'agent:'.$this->agent->id.':sources:text:'.$this->sourceFile->id.':page:'.$this->page->pageNumber;
    }
}
