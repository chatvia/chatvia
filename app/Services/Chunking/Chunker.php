<?php

namespace App\Services\Chunking;

use App\Data\Ingestion\ChunkIngestionData;
use App\Services\Splitters\RecursiveCharacterTextSplitter;
use App\Services\TikToken;
use Illuminate\Http\File;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

readonly class Chunker
{
    public function __construct(
        private int $agentId,
        private string $chunkableType,
        private int $chunkableId,
        private int $maxTokensPerChunk = 800,
    ) {}

    public function createChunks(array $parsedData): Collection
    {
        $chunks = collect();
        $chunkIndex = 1;
        $textBuffer = '';
        $imageBuffer = [];

        foreach ($parsedData as $pageIndex => $page) {
            $pageText = $page['text'] ?? '';
            $pageImages = $page['images'] ?? [];

            // Skip empty pages
            if (empty(trim($pageText))) {
                continue;
            }

            // Add to buffer
            $textBuffer .= ($textBuffer ? "\n\n" : '').$pageText;
            $imageBuffer = array_merge($imageBuffer, $this->storePageImages($pageImages));

            // Check if buffer exceeds token limit
            $bufferTokenCount = TikToken::countTokens($textBuffer);

            if ($bufferTokenCount >= $this->maxTokensPerChunk) {
                // Process the buffer
                $bufferChunks = $this->processBuffer($textBuffer, $imageBuffer, $chunkIndex);
                $chunks = $chunks->merge($bufferChunks);
                $chunkIndex += $bufferChunks->count();

                // Reset buffer
                $textBuffer = '';
                $imageBuffer = [];
            }
        }

        // Process any remaining buffer
        if (! empty(trim($textBuffer))) {
            $bufferChunks = $this->processBuffer($textBuffer, $imageBuffer, $chunkIndex);
            $chunks = $chunks->merge($bufferChunks);
        }

        return $this->setChunkRelationships($chunks);
    }

    private function processBuffer(string $text, array $images, int $startChunkIndex): Collection
    {
        $chunks = collect();
        $tokenCount = TikToken::countTokens($text);

        if ($tokenCount <= $this->maxTokensPerChunk) {
            // Text fits in a single chunk
            $chunks->push($this->createChunk($text, $images, $startChunkIndex));
        } else {
            // Text needs to be split
            $textSplitter = new RecursiveCharacterTextSplitter(
                chunkSize: $this->maxTokensPerChunk,
                chunkOverlap: 0,
            );

            $textChunks = $textSplitter->splitText($text);

            foreach ($textChunks as $index => $chunkText) {
                $chunks->push($this->createChunk($chunkText, $images, $startChunkIndex + $index));
            }
        }

        return $chunks;
    }

    private function createChunk(string $text, array $images, int $chunkIndex): ChunkIngestionData
    {
        return ChunkIngestionData::from([
            'agent_id' => $this->agentId,
            'chunkable_type' => $this->chunkableType,
            'chunkable_id' => $this->chunkableId,
            'chunk_index' => $chunkIndex,
            'expected_chunks' => 0, // Will be set later
            'chunk_text' => trim($text),
            'chunk_images' => $images,
            'prev_chunk' => '',
            'next_chunk' => '',
        ]);
    }

    private function storePageImages(array $pageImages): array
    {
        $imagePaths = [];

        foreach ($pageImages as $image) {
            $imagePaths[] = Storage::putFileAs(
                path: 'users/'.$this->source->user_id.'/agents/'.$this->source->user_id.'/images',
                file: new File($image['path']),
                name: $image['name'],
            );
        }

        return $imagePaths;
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
                'prev_chunk' => $prevChunk,
                'next_chunk' => $nextChunk,
            ]);
        });
    }
}
