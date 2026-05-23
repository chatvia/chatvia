<?php

namespace App\Jobs\Ingestion\Website;

use App\Data\Ingestion\OCR\PageData;
use App\Models\Agent;
use App\Models\AgentWebsiteUrl;
use App\Models\Chunk;
use App\Services\ChunkEnhanceText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class WebsiteUrlEnhanceJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [10, 60, 300];

    public bool $deleteWhenMissingModels = true;

    public int $timeout = 800;

    public function __construct(
        public Agent $agent,
        public AgentWebsiteUrl $websiteUrl,
        private readonly Chunk $chunk,
    ) {}

    public function handle(ChunkEnhanceText $enhanceText): void
    {
        $page = new PageData(
            pageNumber: $this->chunk->chunk_index,
            content: $this->chunk->chunk,
            checksum: $this->chunk->checksum,
        );

        $enhanceText->enhanceText(
            $this->chunk,
            $page,
        );
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
