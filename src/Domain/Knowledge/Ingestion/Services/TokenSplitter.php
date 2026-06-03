<?php

namespace Domain\Knowledge\Ingestion\Services;

use Domain\Knowledge\Ingestion\Data\OCR\PageData;
use Domain\Knowledge\Ingestion\Services\Tidy\TextCleaner;

class TokenSplitter
{
    public function __construct(
        private int $chunkSize = 1000,
        private int $chunkOverlap = 100,
        private bool $normalize = true
    ) {}

    public function split(string $text): array
    {
        if ($this->normalize) {
            $text = $this->normalizeText($text);
        }

        // If the entire text fits in a single chunk, no need to split
        $totalTokens = TikToken::countTokens($text);
        if ($totalTokens <= $this->chunkSize) {
            return [$this->createChunk($text, $totalTokens, 1)];
        }

        // Split into paragraphs first
        $paragraphs = preg_split('/\n\s*\n/', $text, -1, PREG_SPLIT_NO_EMPTY);

        $chunks = [];
        $currentChunk = '';
        $currentTokens = 0;
        $chunkIndex = 1;

        foreach ($paragraphs as $paragraph) {
            $paragraph = trim($paragraph);
            if ($paragraph === '') {
                continue;
            }

            $paragraphTokens = TikToken::countTokens($paragraph);

            // If paragraph alone exceeds chunk size, split it by sentences
            if ($paragraphTokens > $this->chunkSize) {
                // Flush current chunk first
                if ($currentChunk !== '') {
                    $chunks[] = $this->createChunk($currentChunk, $currentTokens, $chunkIndex++);
                    $currentChunk = '';
                    $currentTokens = 0;
                }

                // Split large paragraph into sentence-based chunks
                $this->splitParagraphIntoChunks($paragraph, $chunks, $chunkIndex);

                continue;
            }

            // Try to add paragraph to current chunk
            $separator = $currentChunk !== '' ? "\n\n" : '';
            $potential = $currentChunk.$separator.$paragraph;
            $potentialTokens = TikToken::countTokens($potential);

            if ($potentialTokens <= $this->chunkSize) {
                $currentChunk = $potential;
                $currentTokens = $potentialTokens;
            } else {
                // Save current chunk and start new one
                if ($currentChunk !== '') {
                    $chunks[] = $this->createChunk($currentChunk, $currentTokens, $chunkIndex++);
                }
                $currentChunk = $paragraph;
                $currentTokens = $paragraphTokens;
            }
        }

        // Don't forget the last chunk
        if ($currentChunk !== '') {
            $chunks[] = $this->createChunk($currentChunk, $currentTokens, $chunkIndex++);
        }

        // Apply overlap if requested
        if ($this->chunkOverlap > 0 && count($chunks) > 1) {
            $chunks = $this->applyOverlap($chunks);
        }

        return $chunks;
    }

    /**
     * Split a large paragraph by sentences.
     */
    private function splitParagraphIntoChunks(string $paragraph, array &$chunks, int &$chunkIndex): void
    {
        // Split by sentence endings, keeping the delimiter
        $sentences = preg_split('/(?<=[.!?])\s+/', $paragraph, -1, PREG_SPLIT_NO_EMPTY);

        $currentChunk = '';
        $currentTokens = 0;

        foreach ($sentences as $sentence) {
            $sentence = trim($sentence);
            if ($sentence === '') {
                continue;
            }

            $sentenceTokens = TikToken::countTokens($sentence);

            // If a single sentence exceeds chunk size, just add it as-is
            // (better to have a slightly oversized chunk than cut mid-sentence)
            if ($sentenceTokens > $this->chunkSize) {
                if ($currentChunk !== '') {
                    $chunks[] = $this->createChunk($currentChunk, $currentTokens, $chunkIndex++);
                    $currentChunk = '';
                    $currentTokens = 0;
                }
                $chunks[] = $this->createChunk($sentence, $sentenceTokens, $chunkIndex++);

                continue;
            }

            // Try to add sentence to current chunk
            $separator = $currentChunk !== '' ? ' ' : '';
            $potential = $currentChunk.$separator.$sentence;
            $potentialTokens = TikToken::countTokens($potential);

            if ($potentialTokens <= $this->chunkSize) {
                $currentChunk = $potential;
                $currentTokens = $potentialTokens;
            } else {
                // Save current chunk and start new one
                if ($currentChunk !== '') {
                    $chunks[] = $this->createChunk($currentChunk, $currentTokens, $chunkIndex++);
                }
                $currentChunk = $sentence;
                $currentTokens = $sentenceTokens;
            }
        }

        // Don't forget the last chunk from this paragraph
        if ($currentChunk !== '') {
            $chunks[] = $this->createChunk($currentChunk, $currentTokens, $chunkIndex++);
        }
    }

    /**
     * Apply overlap by prepending context from previous chunk.
     */
    private function applyOverlap(array $chunks): array
    {
        if (count($chunks) <= 1) {
            return $chunks;
        }

        $result = [$chunks[0]];

        for ($i = 1; $i < count($chunks); $i++) {
            /** @var PageData $prevPageData */
            $prevPageData = $chunks[$i - 1];
            /** @var PageData $currentPageData */
            $currentPageData = $chunks[$i];

            // Get overlap text from end of previous chunk
            $overlapText = $this->getOverlapText($prevPageData->content);

            if ($overlapText !== '') {
                $newChunk = $overlapText."\n\n".$currentPageData->content;
                $result[] = $this->createChunk($newChunk, TikToken::countTokens($newChunk), $currentPageData->pageNumber);
            } else {
                $result[] = $currentPageData;
            }
        }

        return $result;
    }

    /**
     * Get approximately chunkOverlap tokens worth of text from end of chunk.
     */
    private function getOverlapText(string $text): string
    {
        $tokens = TikToken::encode($text);
        $totalTokens = count($tokens);

        if ($totalTokens <= $this->chunkOverlap) {
            return $text;
        }

        // Get last N tokens
        $overlapTokens = array_slice($tokens, -$this->chunkOverlap);
        $overlapText = TikToken::decode($overlapTokens);

        // Try to find a sentence boundary to start cleanly
        // Look for ". " or ".\n" followed by a capital letter (start of new sentence)
        if (preg_match('/[.!?][\s\n]+[A-Z]/', $overlapText, $matches, PREG_OFFSET_CAPTURE)) {
            $startPos = $matches[0][1] + strlen($matches[0][0]) - 1; // Position of capital letter
            $overlapText = substr($overlapText, $startPos);
        } else {
            // No sentence boundary found, try to at least start at a word boundary
            $firstSpace = strpos($overlapText, ' ');
            if ($firstSpace !== false && $firstSpace > 0) {
                $overlapText = substr($overlapText, $firstSpace + 1);
            }
        }

        return trim($overlapText);
    }

    private function createChunk(string $text, int $tokens, int $chunkIndex): PageData
    {
        return new PageData(
            pageNumber: $chunkIndex,
            content: trim($text),
            checksum: hash('xxh128', $text),
            tokens: $tokens,
        );
    }

    /**
     * Normalize text by removing excessive whitespaces, newlines, and non-UTF8 characters
     */
    private function normalizeText(string $text): string
    {
        $text = TextCleaner::clean($text);

        // Remove excessive newlines (3+ consecutive newlines become 2)
        $text = preg_replace("/\n{3,}/", "\n\n", $text);

        // Remove excessive spaces (2+ consecutive spaces become 1)
        $text = preg_replace('/ {2,}/', ' ', $text);

        // Remove trailing spaces at the end of lines
        $text = preg_replace('/ +$/m', '', $text);

        // Remove leading spaces at the beginning of lines
        $text = preg_replace('/^ {2,}/m', ' ', $text);

        return trim($text);
    }
}
