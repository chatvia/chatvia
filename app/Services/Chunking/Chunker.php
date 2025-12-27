<?php

namespace App\Services\Chunking;

use App\Contracts\ChunkableContract;
use App\Data\Ingestion\ChunkIngestionData;
use App\Data\Ingestion\OCR\PageData;
use App\Models\Agent;
use App\Services\Splitters\RecursiveCharacterTextSplitter;
use App\Services\TikToken;
use Illuminate\Http\File;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Interfaces\EncodedImageInterface;
use Intervention\Image\Laravel\Facades\Image;

readonly class Chunker
{
    public function __construct(
        private Agent $agent,
        private ChunkableContract $chunkable,
        private int $maxTokensPerChunk = 800,
        private int $minTokensPerChunk = 128,
    ) {}

    /**
     * @param  Collection<int, PageData>  $pages
     * @return Collection<int, ChunkIngestionData>
     */
    public function createChunks(Collection $pages): Collection
    {
        $chunks = collect();
        $chunkIndex = 1;
        $textBuffer = '';
        $imageBuffer = [];
        $tableBuffer = [];

        foreach ($pages as $page) {
            $pageText = $page->content;
            $pageImages = $page->images;
            $pageTables = $page->tables;

            // Skip empty pages
            if (empty(trim($pageText))) {
                continue;
            }

            // Add to buffer
            $textBuffer .= ($textBuffer ? "\n\n" : '').$pageText;
            $imageBuffer = array_merge($imageBuffer, $this->storePageImages($pageImages));
            $tableBuffer = array_merge($tableBuffer, $pageTables);

            // Check if buffer exceeds token limit
            $bufferTokenCount = TikToken::countTokens($textBuffer);

            if ($bufferTokenCount >= $this->maxTokensPerChunk) {
                // Process the buffer
                $bufferChunks = $this->processBuffer($textBuffer, $imageBuffer, $tableBuffer, $chunkIndex);
                $chunks = $chunks->merge($bufferChunks);
                $chunkIndex += $bufferChunks->count();

                // Reset buffer
                $textBuffer = '';
                $imageBuffer = [];
                $tableBuffer = [];
            }
        }

        // Process any remaining buffer
        if (! empty(trim($textBuffer))) {
            $bufferChunks = $this->processBuffer($textBuffer, $imageBuffer, $tableBuffer, $chunkIndex);
            $chunks = $chunks->merge($bufferChunks);
        }

        // Merge small chunks that are below the minimum token threshold
        $chunks = $this->mergeSmallChunks($chunks);

        return $this->setChunkRelationships($chunks);
    }

    private function processBuffer(string $text, array $images, array $tables, int $startChunkIndex): Collection
    {
        $chunks = collect();
        $tokenCount = TikToken::countTokens($text);

        if ($tokenCount <= $this->maxTokensPerChunk) {
            // Text fits in a single chunk
            $chunks->push($this->createChunk($text, $images, $tables, $startChunkIndex));
        } else {
            // Text needs to be split
            $textSplitter = new RecursiveCharacterTextSplitter(
                chunkSize: $this->maxTokensPerChunk,
                chunkOverlap: 0,
            );

            $textChunks = $textSplitter->splitText($text);

            foreach ($textChunks as $index => $chunkText) {
                $chunks->push($this->createChunk($chunkText, $images, $tables, $startChunkIndex + $index));
            }
        }

        return $chunks;
    }

    private function createChunk(string $text, array $images, array $tables, int $chunkIndex): ChunkIngestionData
    {
        return ChunkIngestionData::from([
            'agent_id' => $this->agent->id,
            'chunkable_type' => $this->chunkable::class,
            'chunkable_id' => $this->chunkable->id,
            'chunk_index' => $chunkIndex,
            'expected_chunks' => 0, // Will be set later
            'chunk_text' => trim($text),
            'chunk_images' => $images,
            'chunk_tables' => $tables,
            'prev_chunk' => '',
            'next_chunk' => '',
        ]);
    }

    /**
     * @param  array<int, EncodedImageInterface>  $images
     */
    private function storePageImages(array $images): array
    {
        $imagePaths = [];

        $userId = $this->agent->user_id;

        foreach ($images as $imageBase64) {
            $fileName = Str::uuid()->toString().'.png';
            $filepath = create_temp_file(
                $fileName,
                Image::read($imageBase64)->toPng()->toString()
            );
            $imagePaths[] = Storage::putFileAs(
                path: 'users/'.$userId.'/agents/'.$this->agent->id.'/images',
                file: new File($filepath),
                name: $fileName,
            );
        }

        return $imagePaths;
    }

    private function mergeSmallChunks(Collection $chunks): Collection
    {
        $mergedChunks = collect();
        $totalChunks = $chunks->count();
        $i = 0;

        while ($i < $totalChunks) {
            $chunk = $chunks->get($i);
            $tokenCount = TikToken::countTokens($chunk->chunk_text);

            if ($tokenCount >= $this->minTokensPerChunk) {
                // Chunk is large enough, keep it
                $mergedChunks->push($chunk);
                $i++;
            } else {
                // Chunk is too small, try to merge with next chunk first
                if ($i < $totalChunks - 1) {
                    $nextChunk = $chunks->get($i + 1);
                    $mergedChunk = $this->mergeChunks($chunk, $nextChunk);
                    $mergedChunks->push($mergedChunk);
                    $i += 2; // Skip the next chunk since it's been merged
                } // If no next chunk, try to merge with previous
                elseif ($mergedChunks->isNotEmpty()) {
                    $prevChunk = $mergedChunks->pop();
                    $mergedChunk = $this->mergeChunks($prevChunk, $chunk);
                    $mergedChunks->push($mergedChunk);
                    $i++;
                } else {
                    // Edge case: only one chunk and it's too small, keep it
                    $mergedChunks->push($chunk);
                    $i++;
                }
            }
        }

        // Recalculate chunk indices
        return $mergedChunks->map(function (ChunkIngestionData $chunk, $newIndex) {
            return ChunkIngestionData::from([
                'agent_id' => $chunk->agent_id,
                'chunkable_type' => $chunk->chunkable_type,
                'chunkable_id' => $chunk->chunkable_id,
                'chunk_index' => $newIndex + 1, // 1-based indexing
                'expected_chunks' => 0, // Will be set in setChunkRelationships
                'chunk_text' => $chunk->chunk_text,
                'chunk_images' => $chunk->chunk_images,
                'chunk_tables' => $chunk->chunk_tables,
                'prev_chunk' => $chunk->prev_chunk,
                'next_chunk' => $chunk->next_chunk,
            ]);
        });
    }

    private function mergeChunks(ChunkIngestionData $chunk1, ChunkIngestionData $chunk2): ChunkIngestionData
    {
        return ChunkIngestionData::from([
            'agent_id' => $chunk1->agent_id,
            'chunkable_type' => $chunk1->chunkable_type,
            'chunkable_id' => $chunk1->chunkable_id,
            'chunk_index' => min($chunk1->chunk_index, $chunk2->chunk_index), // Will be recalculated
            'expected_chunks' => 0, // Will be set later
            'chunk_text' => $chunk1->chunk_text."\n\n".$chunk2->chunk_text,
            'chunk_images' => array_merge($chunk1->chunk_images, $chunk2->chunk_images),
            'chunk_tables' => array_merge($chunk1->chunk_tables, $chunk2->chunk_tables),
            'prev_chunk' => $chunk1->prev_chunk,
            'next_chunk' => $chunk2->next_chunk,
        ]);
    }

    private function setChunkRelationships(Collection $chunks): Collection
    {
        $totalChunks = $chunks->count();

        return $chunks->map(function (ChunkIngestionData $chunk, $index) use ($totalChunks, $chunks) {
            $prevChunk = $index > 0 ? $chunks->get($index - 1)->chunk_text : '';
            $nextChunk = $index < $totalChunks - 1 ? $chunks->get($index + 1)->chunk_text : '';

            return ChunkIngestionData::from([
                'agent_id' => $chunk->agent_id,
                'chunkable_type' => $chunk->chunkable_type,
                'chunkable_id' => $chunk->chunkable_id,
                'chunk_index' => $chunk->chunk_index,
                'expected_chunks' => $totalChunks,
                'chunk_text' => $chunk->chunk_text,
                'chunk_images' => $chunk->chunk_images,
                'chunk_tables' => $chunk->chunk_tables,
                'prev_chunk' => $prevChunk,
                'next_chunk' => $nextChunk,
            ]);
        });
    }
}
