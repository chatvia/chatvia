<?php

namespace App\Jobs\Ingestion\Media;

use App\Data\Ingestion\OCR\PageData;
use App\Models\Agent;
use App\Models\Chunk;
use App\Models\SourceFile;
use App\Services\ChunkEnhanceText;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class MediaEnhanceChunkJob implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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

    public function handle(ChunkEnhanceText $enhanceText): void
    {
        $enhanceText->enhanceText(
            $this->chunk,
            $this->page,
            $this->previousPage,
            $this->nextPage,
        );
    }

    public function uniqueId()
    {
        return 'agents:'.$this->agent->id.':sources:file:'.$this->sourceFile->id.':checksum:'.$this->page->checksum;
    }
}
