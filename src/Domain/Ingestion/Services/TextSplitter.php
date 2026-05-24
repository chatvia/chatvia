<?php

namespace Domain\Ingestion\Services;

class TextSplitter
{
    public function __construct(
        private int $chunkSize = 1000,
        private int $chunkOverlap = 200,
        private array $separators = ["\n\n", "\n", '. ', ', ', ' ', '']
    ) {}

    public function split(string $text): array
    {
        $chunks = [];
        $this->splitRecursively($text, 0, $chunks);

        return $this->mergeSmallChunks($chunks);
    }

    private function splitRecursively(string $text, int $separatorIndex, array &$chunks): void
    {
        // Base case: text fits in chunk size
        if (mb_strlen($text, 'UTF-8') <= $this->chunkSize) {
            if (trim($text) !== '') {
                $chunks[] = [
                    'chunk' => trim($text),
                    'tokens' => TikToken::countTokens($text),
                ];
            }

            return;
        }

        // Base case: no more separators, force split by characters
        if ($separatorIndex >= count($this->separators)) {
            foreach ($this->mbStrSplit($text, $this->chunkSize) as $part) {
                if (trim($part) !== '') {
                    $chunks[] = [
                        'chunk' => trim($part),
                        'tokens' => TikToken::countTokens($part),
                    ];
                }
            }

            return;
        }

        $separator = $this->separators[$separatorIndex];

        // If separator is empty string, move to character splitting
        if ($separator === '') {
            $this->splitRecursively($text, $separatorIndex + 1, $chunks);

            return;
        }

        $parts = explode($separator, $text);

        // If no split happened, try next separator
        if (count($parts) === 1) {
            $this->splitRecursively($text, $separatorIndex + 1, $chunks);

            return;
        }

        $currentChunk = '';

        foreach ($parts as $i => $part) {
            $partWithSeparator = $i < count($parts) - 1 ? $part.$separator : $part;
            $potential = $currentChunk.$partWithSeparator;

            if (mb_strlen($potential, 'UTF-8') <= $this->chunkSize) {
                $currentChunk = $potential;
            } else {
                // Save current chunk if not empty
                if (trim($currentChunk) !== '') {
                    $chunks[] = trim($currentChunk);
                }

                // If single part exceeds chunk size, split it further
                if (mb_strlen($partWithSeparator, 'UTF-8') > $this->chunkSize) {
                    $this->splitRecursively($partWithSeparator, $separatorIndex + 1, $chunks);
                    $currentChunk = '';
                } else {
                    $currentChunk = $partWithSeparator;
                }
            }
        }

        // Don't forget the last chunk
        if (trim($currentChunk) !== '') {
            $chunks[] = trim($currentChunk);
        }
    }

    private function mergeSmallChunks(array $chunks): array
    {
        if (count($chunks) <= 1) {
            return $chunks;
        }

        $merged = [];
        $current = '';

        foreach ($chunks as $chunk) {
            if ($current === '') {
                $current = $chunk;
            } elseif (mb_strlen($current, 'UTF-8') + mb_strlen($chunk, 'UTF-8') + 1 <= $this->chunkSize) {
                $current .= ' '.$chunk;
            } else {
                $merged[] = $current;
                $current = $chunk;
            }
        }

        if ($current !== '') {
            $merged[] = $current;
        }

        return $merged;
    }

    public function splitWithOverlap(string $text): array
    {
        $chunks = $this->split($text);

        if ($this->chunkOverlap === 0 || count($chunks) <= 1) {
            return $chunks;
        }

        $result = [];
        foreach ($chunks as $i => $chunk) {
            if ($i > 0 && $this->chunkOverlap > 0) {
                $prevChunk = $chunks[$i - 1];
                $overlapText = $this->getOverlapFromEnd($prevChunk);
                $chunk = $overlapText.' '.$chunk;
            }
            $result[] = $chunk;
        }

        return $result;
    }

    private function getOverlapFromEnd(string $text): string
    {
        if (mb_strlen($text, 'UTF-8') <= $this->chunkOverlap) {
            return $text;
        }

        $overlap = mb_substr($text, -$this->chunkOverlap, null, 'UTF-8');

        // Try to start at a word boundary
        $spacePos = mb_strpos($overlap, ' ', 0, 'UTF-8');
        if ($spacePos !== false) {
            $overlap = mb_substr($overlap, $spacePos + 1, null, 'UTF-8');
        }

        return $overlap;
    }

    /**
     * UTF-8 safe string splitting by character count
     */
    private function mbStrSplit(string $text, int $length): array
    {
        $result = [];
        $textLength = mb_strlen($text, 'UTF-8');

        for ($i = 0; $i < $textLength; $i += $length) {
            $result[] = mb_substr($text, $i, $length, 'UTF-8');
        }

        return $result;
    }
}
