<?php

namespace Domain\Knowledge\Ingestion\Jobs\Website;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Actions\Chunks\EnhanceChunkAction;
use Domain\Knowledge\Ingestion\Data\Chunking\EnhanceChunkData;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Source\Web\Models\AgentWebsiteUrl;
use Domain\Llm\Usage\AttributesTokenUsage;
use Domain\Llm\Usage\TracksTokenUsage;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/**
 * Enhances one chunk of a scraped web page, from its text only. See
 * {@see EnhanceChunkAction}.
 */
class WebsiteUrlEnhanceJob implements AttributesTokenUsage, ShouldBeUnique, ShouldQueue
{
    use Queueable, TracksTokenUsage;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public bool $deleteWhenMissingModels = true;

    public int $timeout = 800;

    public function __construct(
        public Agent $agent,
        public AgentWebsiteUrl $websiteUrl,
        public Chunk $chunk,
    ) {}

    public function tokenWorkspaceId(): ?int
    {
        return $this->agent->user_id;
    }

    public function handle(EnhanceChunkAction $action): void
    {
        $action->execute(new EnhanceChunkData(
            chunk: $this->chunk,
            page: PageData::fromChunkModel($this->chunk),
        ));
    }

    public function failed(Throwable $e): void
    {
        report($e);
    }

    public function uniqueId(): string
    {
        return 'agent:'.$this->agent->id.':sources:websites:urls:'.$this->websiteUrl->id.':chunk:'.$this->chunk->chunk_index;
    }
}
