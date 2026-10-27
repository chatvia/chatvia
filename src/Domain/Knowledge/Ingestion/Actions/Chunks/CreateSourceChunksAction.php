<?php

namespace Domain\Knowledge\Ingestion\Actions\Chunks;

use Domain\Knowledge\Embedding\Contracts\EmbeddingsProvider;
use Domain\Knowledge\Ingestion\Data\Chunking\ChunkPagesData;
use Domain\Knowledge\Ingestion\Data\Chunking\EnhanceChunkData;
use Domain\Knowledge\Ingestion\Data\Chunking\EnhancedChunkData;
use Domain\Knowledge\Ingestion\Data\Ocr\PageData;
use Domain\Knowledge\Ingestion\Enums\ChunkEnhancement;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Llm\Services\TikToken;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

/**
 * Stores pages of a source (file, text, transcript or website) as chunks.
 *
 * Pages the source already holds (same checksum) are skipped before anything
 * is paid for: duplicate and blank pages are common. The rest are enhanced
 * when asked, embedded in one request, and written with one multi-row insert,
 * so each chunk row is written once. `chunk_index` is the page number and is
 * only set here. Two jobs storing the same page at once are absorbed by the
 * unique (chunkable, checksum) index.
 *
 * A page held without its enhancement while the source asks for one is the
 * exception: a chunk job that failed every try stores its pages plain, so a
 * later run enhances those pages and updates their rows.
 */
class CreateSourceChunksAction
{
    public function __construct(
        private readonly EmbeddingsProvider $embeddings,
        private readonly EnhanceChunkAction $enhance,
    ) {}

    /**
     * @return Collection<string, int> the chunks this call created: checksum => id
     */
    public function execute(ChunkPagesData $data): Collection
    {
        $pages = collect($data->group->pages)->unique(fn (PageData $page): string => $page->checksum);

        // checksum => whether the stored row has its enhancement
        $stored = Chunk::query()
            ->ofSource($data->source)
            ->whereIn('checksum', $pages->pluck('checksum')->all())
            ->pluck('is_enhanced', 'checksum');

        $pages = $pages->reject(fn (PageData $page): bool => $stored->has($page->checksum)
            && ($stored[$page->checksum] || $data->enhancement === ChunkEnhancement::Off))->all();

        if ($pages === []) {
            return collect();
        }

        $enhanced = $this->enhancements($data, $pages);
        [$plain, $new] = collect($this->rows($data, $pages, $enhanced))
            ->partition(fn (array $row): bool => $stored->has($row['checksum']));

        $plain->each($this->addEnhancement(...));

        return Chunk::query()
            ->toBase()
            ->insertOrIgnoreReturning($new->values()->all(), ['id', 'checksum'])
            ->mapWithKeys(fn (object $row): array => [(string) $row->checksum => (int) $row->id]);
    }

    /**
     * Write a page's enhancement onto the row that holds it plain. A row
     * another job enhanced in the meantime is left as it is.
     *
     * @param  array<string, mixed>  $row  an insert row from {@see rows()}
     */
    private function addEnhancement(array $row): void
    {
        Chunk::query()
            ->toBase()
            ->where('chunkable_type', $row['chunkable_type'])
            ->where('chunkable_id', $row['chunkable_id'])
            ->where('checksum', $row['checksum'])
            ->where('is_enhanced', false)
            ->update(Arr::only($row, [
                'summary', 'is_enhanced', 'chunked_using_model', 'embedding', 'search_text',
                'questions', 'questions_embedding', 'metadata', 'updated_at',
            ]));
    }

    /**
     * @param  array<int, PageData>  $pages
     * @return array<int, EnhancedChunkData|null> by the page's position in the group
     */
    private function enhancements(ChunkPagesData $data, array $pages): array
    {
        $isDocument = $data->enhancement === ChunkEnhancement::Document;
        $enhanced = [];

        foreach ($pages as $position => $page) {
            $enhanced[$position] = $data->enhancement === ChunkEnhancement::Off ? null : $this->enhance->execute(new EnhanceChunkData(
                page: $page,
                previousPage: $isDocument ? $data->group->before($position) : null,
                nextPage: $isDocument ? $data->group->after($position) : null,
                imageDirectory: $isDocument ? $data->imageDirectory : null,
            ));
        }

        return $enhanced;
    }

    /**
     * One insert row per page. Every row has the same columns, as a multi-row
     * insert needs.
     *
     * @param  array<int, PageData>  $pages
     * @param  array<int, EnhancedChunkData|null>  $enhanced
     * @return list<array<string, mixed>>
     */
    private function rows(ChunkPagesData $data, array $pages, array $enhanced): array
    {
        $embeddingTexts = [];

        foreach ($pages as $position => $page) {
            $embeddingTexts[$position] = $enhanced[$position]->embeddingText ?? $page->content;
        }

        $vectors = array_combine(array_keys($embeddingTexts), $this->embeddings->embedBatch(array_values($embeddingTexts)));
        $tokens = array_map(static fn (PageData $page): int => TikToken::countTokens($page->content), $pages);
        $questionVectors = $this->questionVectors($enhanced);
        $now = now();
        $rows = [];

        foreach ($pages as $position => $page) {
            $enhancement = $enhanced[$position];
            $questions = $enhancement?->questions;

            $rows[] = [
                'external_id' => (string) Str::uuid7(),
                'workspace_id' => $data->source->workspace_id,
                'chunkable_type' => $data->source->getMorphClass(),
                'chunkable_id' => $data->source->getKey(),
                'chunk_index' => $page->pageNumber,
                'chunk' => $page->content,
                'summary' => $enhancement?->summary,
                'is_enhanced' => $enhancement !== null,
                'chunked_using_model' => $enhancement?->model,
                'tokens' => $tokens[$position],
                'embedding' => $this->vectorLiteral($vectors[$position]),
                'search_text' => $embeddingTexts[$position],
                'questions' => $questions,
                'questions_embedding' => $questions !== null ? $this->vectorLiteral($questionVectors[$questions]) : null,
                'metadata' => json_encode($enhancement->metadata ?? [], JSON_THROW_ON_ERROR),
                'checksum' => $page->checksum,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        return $rows;
    }

    /**
     * The questions of every enhanced page, embedded in one request.
     *
     * @param  array<int, EnhancedChunkData|null>  $enhanced
     * @return array<string, array<int, float>> questions => vector
     */
    private function questionVectors(array $enhanced): array
    {
        $questions = array_values(array_unique(array_filter(
            array_map(static fn (?EnhancedChunkData $enhancement): ?string => $enhancement?->questions, $enhanced),
        )));

        if ($questions === []) {
            return [];
        }

        return array_combine($questions, $this->embeddings->embedBatch($questions));
    }

    /**
     * @param  array<int, float>  $vector
     */
    private function vectorLiteral(array $vector): string
    {
        return '['.implode(',', $vector).']';
    }
}
