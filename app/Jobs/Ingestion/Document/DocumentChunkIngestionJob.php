<?php

namespace App\Jobs\Ingestion\Document;

use App\Contracts\EmbeddingsProviderInterface;
use App\Data\Ingestion\OCR\PageData;
use App\Events\FileIngestionProgressEvent;
use App\Models\Agent;
use App\Models\Chunk;
use App\Models\SourceFile;
use App\Services\TikToken;
use Illuminate\Bus\Batchable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\DB;

class DocumentChunkIngestionJob implements ShouldQueue
{
    use Batchable, Queueable;

    public bool $deleteWhenMissingModels = true;

    public int $tries = 1;

    public int $backoff = 10;

    public function __construct(
        public Agent $agent,
        public SourceFile $sourceFile,
        public PageData $page,
        public ?PageData $previousPage = null,
        public ?PageData $nextPage = null,
    ) {}

    public function handle(): void
    {
        if ($this->batch()?->cancelled()) {
            return;
        }

        if (Chunk::query()
            ->where('chunkable_type', $this->sourceFile::class)
            ->where('chunkable_id', $this->sourceFile->id)
            ->where('checksum', $this->page->checksum)
            ->exists()) {
            return;
        }

        $chunk = Chunk::query()->create([
            'user_id' => $this->agent->user_id,
            'chunkable_type' => SourceFile::class,
            'chunkable_id' => $this->sourceFile->id,
            'type' => 'text',
            'chunk_index' => $this->page->pageNumber,
            'chunk' => $this->page->content,
            'questions' => [],
            'summary' => '',
            'chunked_using_model' => config('ai.models.chunker'),
            'embedding_model' => app(EmbeddingsProviderInterface::class)->getModelName(),
            'cost_in_cents' => 0,
            'tokens' => TikToken::countTokens($this->page->content),
            'embedding' => embed($this->page->content, true),
            'embedding_bm25' => DB::raw('tokenize('.DB::connection()->getPdo()->quote($this->page->content).", 'llmlingua2')"),
            'metadata' => [],
            'checksum' => $this->page->checksum,
            'retrieval_count' => 0,
        ]);

        $this->sourceFile->refresh();

        FileIngestionProgressEvent::dispatch(
            $this->sourceFile,
            $this->agent->external_id
        );

        DocumentEnhanceChunkJob::dispatchIf(config('ai.chunker.enhance'), $this->agent, $this->sourceFile, $chunk, $this->page, $this->previousPage, $this->nextPage);
    }
}
