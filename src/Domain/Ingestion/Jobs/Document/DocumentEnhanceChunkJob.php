<?php

namespace Domain\Ingestion\Jobs\Document;

use App\LLM\Operations\Chunker;
use App\LLM\Operations\CostCalculator;
use App\LLM\Operations\Tables;
use App\LLM\Operations\Vision;
use Domain\Agents\Models\Agent;
use Domain\Ingestion\Data\OCR\PageData;
use Domain\Ingestion\Models\Chunk;
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
        if ($this->chunk->is_enhanced) {
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
        $questionsText = implode("\n", $enhancedChunk->questions);

        // Build the chunk's embedding input: page content + image/table
        // descriptions (which describe the same source content in prose form).
        $chunkEmbeddingText = $this->page->content;

        if ($describedImages) {
            $chunkEmbeddingText .= "\n\n".implode("\n\n", array_values($describedImages));
        }

        if ($describedTables) {
            $chunkEmbeddingText .= "\n\n".$describedTables;
        }

        // Contextual Retrieval (per Anthropic cookbook): prepend the LLM-generated
        // summary so the vector and BM25 representations include document-level
        // context. The cookbook reports +5pp Pass@10 from this alone. Questions
        // still live in their own column for independent HyDE-style matching.
        $contextualizedChunk = $summary !== ''
            ? $summary."\n\n".$chunkEmbeddingText
            : $chunkEmbeddingText;

        $embeddingPrice = config('ai.pricing.embedding_per_million_tokens', 0.10);
        $costInCents = app(CostCalculator::class)
            ->calculateCostInCents($contextualizedChunk, '', $embeddingPrice, 0.0) + $enhancedChunk->cost;

        if ($questionsText !== '') {
            $costInCents += app(CostCalculator::class)
                ->calculateCostInCents($questionsText, '', $embeddingPrice, 0.0);
        }

        $tables = array_values(array_filter(
            array_map('trim', $this->page->tables),
            fn (string $table): bool => $table !== ''
        ));

        $this->chunk->update([
            'embedding' => embed($contextualizedChunk),
            'embedding_bm25' => Bm25Tokenizer::expression($contextualizedChunk),
            'questions' => $questionsText !== '' ? $questionsText : null,
            'questions_embedding' => $questionsText !== '' ? embed($questionsText, true) : null,
            'questions_embedding_bm25' => $questionsText !== '' ? Bm25Tokenizer::expression($questionsText) : null,
            'cost_in_micro_cents' => CostCalculator::centsToMicroCents($costInCents),
            'summary' => $summary,
            'is_enhanced' => true,
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
