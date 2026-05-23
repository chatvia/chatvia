<?php

namespace App\Jobs\Ingestion\Document;

use App\Data\Ingestion\OCR\PageData;
use App\Models\Agent;
use App\Models\Chunk;
use App\Models\SourceFile;
use App\Services\Infrastructure\LLM\Operations\Chunker;
use App\Services\Infrastructure\LLM\Operations\CostCalculator;
use App\Services\Infrastructure\LLM\Operations\Tables;
use App\Services\Infrastructure\LLM\Operations\Vision;
use App\Services\Search\Bm25Tokenizer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;
use Throwable;

class DocumentEnhanceChunkJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

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

    public function handle(): void
    {
        if ($this->chunk->enhanced) {
            return;
        }

        // Describe and Save Images associated with the page
        $describedImages = [];
        foreach ($this->page->imagesBase64 as $imageBase64) {
            // Download image content and convert to data URI to avoid URL access issues
            $imageContent = Image::read($imageBase64)->toJpeg()->toString();
            $imageDescription = app(Vision::class)->fromDataUrl($imageContent);

            logger()->info(
                'image description: '.$imageDescription.PHP_EOL.'image path: '.$imageBase64.PHP_EOL,
            );

            if ($imageDescription !== false) {
                $imagePath = 'users/'.$this->sourceFile->user_id.'/agents/'.$this->agent->id.'/'.Str::uuid()->toString().'.jpg';
                Storage::put($imagePath, $imageContent);

                $describedImages[$imagePath] = $imageDescription;
            }
        }

        // Describe and save tables associated with the page
        $describedTables = '';
        if ($this->page->tables) {
            $describedTables = app(Tables::class)->describe($this->page->tables);
        }

        // Extract summary and questions from XML tags
        $enhancedChunk = app(Chunker::class)->chunkDocument(
            page: $this->page,
            prevPage: $this->previousPage,
            nextPage: $this->nextPage,
        );

        $summary = $enhancedChunk->summary;
        $questions = $enhancedChunk->questions;

        // Build embedding content with summary and questions
        $embeddingContent = $this->page->content."\n";

        if ($describedImages) {
            $embeddingContent .= implode("\n\n", array_values($describedImages));
        }

        if ($describedTables) {
            $embeddingContent .= $describedTables."\n";
        }

        if ($summary) {
            $embeddingContent .= $summary."\n";
        }

        if ($questions) {
            $embeddingContent .= implode("\n", $questions)."\n";
        }

        // Add embedding cost
        $embeddingPrice = config('ai.pricing.embedding_per_million_tokens', 0.10);
        $costInCents = app(CostCalculator::class)
            ->calculateCostInCents($embeddingContent, '', $embeddingPrice, 0.0) + $enhancedChunk->cost;

        $tables = array_values(array_filter(
            array_map('trim', $this->page->tables),
            fn (string $table) => $table !== ''
        ));

        $this->chunk->update([
            'embedding' => embed($embeddingContent),
            'embedding_bm25' => Bm25Tokenizer::expression($embeddingContent),
            'cost' => $costInCents,
            'summary' => $summary,
            'questions' => $questions,
            'metadata' => [
                'images' => $describedImages,
                'tables' => [
                    'markdown' => $tables,
                    'descriptions' => $describedTables,
                ],
            ],
        ]);
    }

    public function failed(Throwable $e): void
    {
        report($e);
    }
}
