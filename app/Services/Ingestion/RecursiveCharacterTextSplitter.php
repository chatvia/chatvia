<?php

namespace App\Services\Ingestion;

use App\Support\TikToken;

/**
 * A simple, robust, LangChain-inspired text splitter.
 *
 * This splitter works by recursively trying to split text along a series of separators.
 * It aims to keep semantically related pieces of text together as long as possible.
 */
class RecursiveCharacterTextSplitter
{
    public function __construct(
        public int $chunkSize = 700,
        public int $chunkOverlap = 50,
        public array $separators = ["\n\n", "\n", '. ', ' ', ''],
    ) {}

    /**
     * Splits the given text into chunks.
     */
    public function splitText(string $text): array
    {
        $chunks = $this->recursiveSplit($text, $this->separators);

        // Merge small chunks throughout the list
        $mergedChunks = [];
        $minSize = (int) ($this->chunkSize * 0.4); // 40% of chunk size

        for ($i = 0, $iMax = count($chunks); $i < $iMax; $i++) {
            $currentChunk = $chunks[$i];
            $currentTokens = TikToken::countTokens($currentChunk);

            // If this chunk is small and it's not the last chunk
            if ($currentTokens < $minSize && $i < count($chunks) - 1) {
                $nextChunk = $chunks[$i + 1];
                $nextTokens = TikToken::countTokens($nextChunk);

                // If merging with next chunk won't exceed chunk size + 20%, merge them
                if ($currentTokens + $nextTokens <= $this->chunkSize * 1.2) {
                    // Find the appropriate separator to use between chunks
                    $separator = $this->findAppropriateSeparator($currentChunk, $nextChunk);
                    $chunks[$i + 1] = $currentChunk.$separator.$nextChunk;

                    continue; // Skip adding current chunk to merged list
                }
            }

            // If this is the last chunk and it's small, merge with previous
            if ($currentTokens < $minSize && $i == count($chunks) - 1 && count($mergedChunks) > 0) {
                $lastMerged = array_pop($mergedChunks);
                $separator = $this->findAppropriateSeparator($lastMerged, $currentChunk);
                $mergedChunks[] = $lastMerged.$separator.$currentChunk;

                continue;
            }

            $mergedChunks[] = $currentChunk;
        }

        return $mergedChunks;
    }

    /**
     * Find the most appropriate separator to use between two chunks.
     */
    private function findAppropriateSeparator(string $chunk1, string $chunk2): string
    {
        // Check if chunks end/start with common separators
        if (str_ends_with($chunk1, "\n\n") || str_starts_with($chunk2, "\n\n")) {
            return "\n\n";
        }
        if (str_ends_with($chunk1, "\n") || str_starts_with($chunk2, "\n")) {
            return "\n";
        }
        if (str_ends_with($chunk1, '. ') || str_starts_with($chunk2, '. ')) {
            return '. ';
        }

        // Default to space if no clear separator pattern
        return ' ';
    }

    /**
     * Recursively splits text based on the provided separators.
     */
    private function recursiveSplit(string $text, array $separators): array
    {
        $finalChunks = [];
        $text = trim($text);

        if (TikToken::countTokens($text) <= $this->chunkSize) {
            return [$text];
        }

        $separator = array_shift($separators);

        if ($separator === null) {
            return $this->splitByTokenSize($text);
        }

        $parts = explode($separator, $text);
        $currentChunkParts = [];
        $currentChunkTokenCount = 0;

        foreach ($parts as $part) {
            $partTokenCount = TikToken::countTokens($part);

            if ($partTokenCount > $this->chunkSize) {
                if (! empty($currentChunkParts)) {
                    $finalChunks[] = trim(implode($separator, $currentChunkParts));
                    $currentChunkParts = [];
                    $currentChunkTokenCount = 0;
                }

                $subChunks = $this->recursiveSplit($part, $separators);
                $finalChunks = array_merge($finalChunks, $subChunks);
            } elseif ($currentChunkTokenCount + $partTokenCount + TikToken::countTokens($separator) > $this->chunkSize) {
                if (! empty($currentChunkParts)) {
                    $finalChunks[] = trim(implode($separator, $currentChunkParts));
                }
                $currentChunkParts = [$part];
                $currentChunkTokenCount = $partTokenCount;
            } else {
                $currentChunkParts[] = $part;
                $currentChunkTokenCount += $partTokenCount + TikToken::countTokens($separator);
            }
        }

        if (! empty($currentChunkParts)) {
            $finalChunks[] = trim(implode($separator, $currentChunkParts));
        }

        return $this->addOverlap($finalChunks);
    }

    private function splitByTokenSize(string $text): array
    {
        $chunks = [];
        $tokens = TikToken::encode($text);
        $totalTokens = count($tokens);

        for ($i = 0; $i < $totalTokens; $i += $this->chunkSize) {
            $chunkTokens = array_slice($tokens, $i, $this->chunkSize);
            $chunks[] = TikToken::decode($chunkTokens);
        }

        return $chunks;
    }

    private function addOverlap(array $chunks): array
    {
        if ($this->chunkOverlap <= 0 || count($chunks) <= 1) {
            return $chunks;
        }

        $overlappedChunks = [];
        for ($i = 0, $iMax = count($chunks); $i < $iMax; $i++) {
            if ($i > 0) {
                $prevChunk = $chunks[$i - 1];
                $overlapTokens = array_slice(TikToken::encode($prevChunk), -$this->chunkOverlap);
                $overlapText = TikToken::decode($overlapTokens);
                $chunks[$i] = $overlapText.$chunks[$i];
            }
        }

        return $chunks;
    }
}
