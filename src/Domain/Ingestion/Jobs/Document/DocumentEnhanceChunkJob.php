<?php

namespace Domain\Ingestion\Jobs\Document;

use App\LLM\Operations\Chunker;
use App\LLM\Operations\CostCalculator;
use App\LLM\Operations\Tables;
use App\LLM\Operations\Vision;
use Domain\Agents\Models\Agent;
use Domain\Ingestion\Data\OCR\PageData;
use Domain\Ingestion\Models\Chunk;
use Domain\Ingestion\Models\ChunkQuestion;
use Domain\Ingestion\Services\Search\Bm25Tokenizer;
use Domain\Sources\Files\Models\SourceFile;
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

        // Embed chunk content only (with image/table descriptions, which describe
        // the same content). Summary and questions are stored separately to avoid
        // diluting the chunk's vector and confusing the embedding model with mixed
        // intent (chunk = answer, questions = question).
        $chunkEmbeddingText = $this->page->content;

        if ($describedImages) {
            $chunkEmbeddingText .= "\n\n".implode("\n\n", array_values($describedImages));
        }

        if ($describedTables) {
            $chunkEmbeddingText .= "\n\n".$describedTables;
        }

        $embeddingPrice = config('ai.pricing.embedding_per_million_tokens', 0.10);
        $costInCents = app(CostCalculator::class)
            ->calculateCostInCents($chunkEmbeddingText, '', $embeddingPrice, 0.0) + $enhancedChunk->cost;

        // Embed each question separately and embedding cost on top.
        foreach ($questions as $question) {
            $costInCents += app(CostCalculator::class)
                ->calculateCostInCents($question, '', $embeddingPrice, 0.0);
        }

        $tables = array_values(array_filter(
            array_map('trim', $this->page->tables),
            fn (string $table): bool => $table !== ''
        ));

        $this->chunk->update([
            'embedding' => embed($chunkEmbeddingText),
            'embedding_bm25' => Bm25Tokenizer::expression($chunkEmbeddingText),
            'cost_in_cents' => $costInCents,
            'summary' => $summary,
            'enhanced' => true,
            'metadata' => [
                'images' => $describedImages,
                'tables' => [
                    'markdown' => $tables,
                    'descriptions' => $describedTables,
                ],
            ],
        ]);

        // Re-enhance safety: drop any previously-generated question rows for this
        // chunk before fanning out, so retries don't accumulate duplicates.
        ChunkQuestion::query()->where('chunk_id', $this->chunk->id)->delete();

        foreach ($questions as $question) {
            ChunkQuestion::query()->create([
                'chunk_id' => $this->chunk->id,
                'question' => $question,
                'embedding' => embed($question, true),
            ]);
        }
    }

    public function failed(Throwable $e): void
    {
        report($e);
    }
}
