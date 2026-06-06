<?php

namespace Domain\Knowledge\Ingestion\Jobs\Text;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Sources\CreateSourceChunkAction;
use Domain\Knowledge\Ingestion\Data\OCR\PageData;
use Domain\Knowledge\Source\Text\Events\TextIngestionProgressed;
use Domain\Knowledge\Source\Text\Models\SourceText;
use Domain\Llm\Usage\AttributesTokenUsage;
use Domain\Llm\Usage\TracksTokenUsage;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class TextChunkIngestionJob implements ShouldBeUnique, ShouldQueue, AttributesTokenUsage
{
    use Batchable, Queueable, TracksTokenUsage;

    public bool $deleteWhenMissingModels = true;

    public int $tries = 2;

    public function __construct(
        public Agent $agent,
        public SourceText $sourceText,
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

        $chunk = $createChunk->execute($this->agent, $this->sourceText, $this->page);

        if (! $chunk) {
            return;
        }

        TextIngestionProgressed::dispatch($this->sourceText, $this->agent->external_id);

        TextEnhanceChunkJob::dispatch(
            $this->agent,
            $this->sourceText,
            $chunk,
            $this->page,
            $this->previousPage,
            $this->nextPage,
        );
    }

    public function uniqueId(): string
    {
        return 'agent:'.$this->agent->id.':sources:text:'.$this->sourceText->id.':page:'.$this->page->pageNumber;
    }
}
