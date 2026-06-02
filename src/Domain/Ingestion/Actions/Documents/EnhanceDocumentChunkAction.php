<?php

namespace Domain\Ingestion\Actions\Documents;

use App\LLM\Operations\Documents\Chunker;
use App\LLM\Operations\Documents\Tables;
use App\LLM\Operations\Media\Vision;
use App\LLM\Operations\Pricing\CostCalculator;
use Domain\Agent\Models\Agent;
use Domain\Ingestion\Data\OCR\PageData;
use Domain\Ingestion\Models\Chunk;
use Domain\Ingestion\Services\Search\Bm25Tokenizer;
use Domain\Source\File\Models\SourceFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Laravel\Facades\Image;

class EnhanceDocumentChunkAction
{
    public function __construct(
        private readonly Vision $vision,
        private readonly Tables $tables,
        private readonly Chunker $chunker,
        private readonly CostCalculator $costCalculator,
    ) {}

    public function execute(
        Agent $agent,
        SourceFile $sourceFile,
        Chunk $chunk,
        PageData $page,
        ?PageData $previousPage = null,
        ?PageData $nextPage = null,
    ): void {
        if ($chunk->is_enhanced) {
            return;
        }

        // Describe and Save Images associated with the page
        $describedImages = [];
        foreach ($page->imagesBase64 as $imageBase64) {
            // Download image content and convert to data URI to avoid URL access issues
            $imageContent = Image::read($imageBase64)->toJpeg()->toString();
            $imageDescription = $this->vision->fromDataUrl($imageContent);

            logger()->info(
                'image description: '.$imageDescription.PHP_EOL.'image path: '.$imageBase64.PHP_EOL,
            );

            if ($imageDescription !== false) {
                $imagePath = 'users/'.$sourceFile->user_id.'/agents/'.$agent->id.'/'.Str::uuid()->toString().'.jpg';
                Storage::put($imagePath, $imageContent);

                $describedImages[$imagePath] = $imageDescription;
            }
        }

        // Describe and save tables associated with the page
        $describedTables = '';
        if ($page->tables) {
            $describedTables = $this->tables->describe($page->tables);
        }

        // Extract summary and questions from XML tags
        $enhancedChunk = $this->chunker->chunkDocument(
            page: $page,
            prevPage: $previousPage,
            nextPage: $nextPage,
        );

        $summary = $enhancedChunk->summary;
        $questionsText = implode("\n", $enhancedChunk->questions);

        // Build the chunk's embedding input: page content + image/table
        // descriptions (which describe the same source content in prose form).
        $chunkEmbeddingText = $page->content;

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
        $costInCents = $this->costCalculator
            ->calculateCostInCents($contextualizedChunk, '', $embeddingPrice, 0.0) + $enhancedChunk->cost;

        if ($questionsText !== '') {
            $costInCents += $this->costCalculator
                ->calculateCostInCents($questionsText, '', $embeddingPrice, 0.0);
        }

        $tables = array_values(array_filter(
            array_map('trim', $page->tables),
            fn (string $table): bool => $table !== ''
        ));

        $chunk->update([
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
}
