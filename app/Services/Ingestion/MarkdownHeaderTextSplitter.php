<?php

namespace App\Services\Ingestion;

use InvalidArgumentException;

/**
 * Splits markdown text based on headers while preserving hierarchy and metadata.
 *
 * This class implements a markdown-aware text splitting algorithm that maintains
 * document structure by splitting on headers and preserving header hierarchy
 * as metadata, similar to LangChain's MarkdownHeaderTextSplitter.
 */
class MarkdownHeaderTextSplitter
{
    private array $headersToSplitOn;

    private bool $stripHeaders;

    private bool $returnEachLine;

    /**
     * Create a new markdown header text splitter instance.
     *
     * @param  array  $headersToSplitOn  Array of header levels to split on (e.g., ['#', '##', '###'])
     * @param  bool  $stripHeaders  Whether to remove headers from the resulting chunks
     * @param  bool  $returnEachLine  Whether to return each line as a separate chunk
     *
     * @throws InvalidArgumentException
     */
    public function __construct(
        array $headersToSplitOn = ['#', '##', '###'],
        bool $stripHeaders = false,
        bool $returnEachLine = false
    ) {
        $this->validateParameters($headersToSplitOn);

        $this->headersToSplitOn = $headersToSplitOn;
        $this->stripHeaders = $stripHeaders;
        $this->returnEachLine = $returnEachLine;
    }

    /**
     * Split markdown text into chunks based on headers.
     *
     * @param  string  $text  The markdown text to split
     * @return array<array{content: string, metadata: array}> Array of chunks with metadata
     *
     * @throws InvalidArgumentException
     */
    public function splitText(string $text): array
    {
        if (empty($text)) {
            return [];
        }

        // Normalize line endings
        $text = str_replace(["\r\n", "\r"], "\n", $text);

        $lines = explode("\n", $text);
        $chunks = [];
        $currentContent = [];
        $currentHeaders = [];

        foreach ($lines as $line) {
            $headerInfo = $this->getHeaderInfo($line);

            if ($headerInfo !== null && in_array($headerInfo['marker'], $this->headersToSplitOn, true)) {
                // Save current chunk if it has content
                if (! empty($currentContent)) {
                    $chunks[] = $this->createChunk($currentContent, $currentHeaders);
                    $currentContent = [];
                }

                // Update header hierarchy
                $this->updateHeaderHierarchy($currentHeaders, $headerInfo);

                // Add header to content if not stripping
                if (! $this->stripHeaders) {
                    $currentContent[] = $line;
                }
            } else {
                // Add line to current content
                $currentContent[] = $line;
            }
        }

        // Don't forget the last chunk
        if (! empty($currentContent)) {
            $chunks[] = $this->createChunk($currentContent, $currentHeaders);
        }

        // Filter out empty chunks
        $chunks = array_filter($chunks, fn ($chunk) => ! empty(trim($chunk['content'])));

        // If returnEachLine is true, further split chunks by lines
        if ($this->returnEachLine) {
            $chunks = $this->splitChunksByLines($chunks);
        }

        return array_values($chunks);
    }

    /**
     * Split text with a callback for memory-efficient processing.
     *
     * @param  string  $text  The markdown text to split
     * @param  callable  $callback  Function to process each chunk as it's created
     * @return int Number of chunks processed
     *
     * @throws InvalidArgumentException
     */
    public function splitTextWithCallback(string $text, callable $callback): int
    {
        $chunks = $this->splitText($text);
        $processedCount = 0;

        foreach ($chunks as $index => $chunk) {
            $callback($chunk, $index);
            $processedCount++;

            // Clear the chunk from memory after processing
            unset($chunks[$index]);
        }

        return $processedCount;
    }

    /**
     * Validate constructor parameters.
     *
     * @throws InvalidArgumentException
     */
    private function validateParameters(array $headersToSplitOn): void
    {
        if (empty($headersToSplitOn)) {
            throw new InvalidArgumentException('At least one header level must be provided');
        }

        foreach ($headersToSplitOn as $header) {
            if (! preg_match('/^#{1,6}$/', $header)) {
                throw new InvalidArgumentException(
                    'Invalid header format. Headers must be between # and ######'
                );
            }
        }
    }

    /**
     * Get header information from a line.
     *
     * @return array{level: int, marker: string, text: string}|null
     */
    private function getHeaderInfo(string $line): ?array
    {
        // Match markdown headers (# to ######)
        if (preg_match('/^(#{1,6})\s+(.*)$/', trim($line), $matches)) {
            return [
                'level' => strlen($matches[1]),
                'marker' => $matches[1],
                'text' => trim($matches[2]),
            ];
        }

        return null;
    }

    /**
     * Update the header hierarchy based on the current header.
     */
    private function updateHeaderHierarchy(array &$currentHeaders, array $headerInfo): void
    {
        $level = $headerInfo['level'];

        // Remove headers at the same level or deeper
        $currentHeaders = array_filter(
            $currentHeaders,
            fn ($h) => $h['level'] < $level
        );

        // Add the new header
        $currentHeaders[] = $headerInfo;
    }

    /**
     * Create a chunk with content and metadata.
     *
     * @param  array<string>  $contentLines
     * @return array{content: string, metadata: array}
     */
    private function createChunk(array $contentLines, array $headers): array
    {
        // Join content lines
        $content = implode("\n", $contentLines);

        // Build metadata from headers
        $metadata = [];
        foreach ($headers as $header) {
            $key = str_repeat('#', $header['level']);
            $metadata[$key] = $header['text'];
        }

        return [
            'content' => trim($content),
            'metadata' => $metadata,
        ];
    }

    /**
     * Split chunks further by lines if returnEachLine is true.
     */
    private function splitChunksByLines(array $chunks): array
    {
        $lineChunks = [];

        foreach ($chunks as $chunk) {
            $lines = explode("\n", $chunk['content']);

            foreach ($lines as $line) {
                if (! empty(trim($line))) {
                    $lineChunks[] = [
                        'content' => trim($line),
                        'metadata' => $chunk['metadata'],
                    ];
                }
            }
        }

        return $lineChunks;
    }

    /**
     * Get statistics about the splitting operation.
     *
     * @return array{
     *     totalChunks: int,
     *     averageChunkSize: float,
     *     minChunkSize: int,
     *     maxChunkSize: int,
     *     totalCharacters: int,
     *     chunkSizes: array<int>,
     *     headerCounts: array<string, int>
     * }
     */
    public function getStatistics(array $chunks): array
    {
        if (empty($chunks)) {
            return [
                'totalChunks' => 0,
                'averageChunkSize' => 0,
                'minChunkSize' => 0,
                'maxChunkSize' => 0,
                'totalCharacters' => 0,
                'chunkSizes' => [],
                'headerCounts' => [],
            ];
        }

        $chunkSizes = array_map(
            fn ($chunk) => mb_strlen($chunk['content']),
            $chunks
        );
        $totalCharacters = array_sum($chunkSizes);

        // Count headers used
        $headerCounts = [];
        foreach ($this->headersToSplitOn as $header) {
            $headerCounts[$header] = 0;
        }

        foreach ($chunks as $chunk) {
            foreach ($chunk['metadata'] as $header => $text) {
                if (isset($headerCounts[$header])) {
                    $headerCounts[$header]++;
                }
            }
        }

        return [
            'totalChunks' => count($chunks),
            'averageChunkSize' => $totalCharacters / count($chunks),
            'minChunkSize' => min($chunkSizes),
            'maxChunkSize' => max($chunkSizes),
            'totalCharacters' => $totalCharacters,
            'chunkSizes' => $chunkSizes,
            'headerCounts' => $headerCounts,
        ];
    }

    /**
     * Combine small chunks that are under a specified size threshold.
     */
    public function combineSmallChunks(array $chunks, int $minChunkSize = 100): array
    {
        if (empty($chunks) || count($chunks) === 1) {
            return $chunks;
        }

        $combinedChunks = [];
        $currentChunk = null;

        foreach ($chunks as $chunk) {
            $chunkSize = mb_strlen($chunk['content']);

            if ($currentChunk === null) {
                $currentChunk = $chunk;
            } elseif ($chunkSize < $minChunkSize || mb_strlen($currentChunk['content']) < $minChunkSize) {
                // Combine with current chunk
                $currentChunk['content'] .= "\n\n".$chunk['content'];

                // Merge metadata (keep the most specific headers)
                foreach ($chunk['metadata'] as $key => $value) {
                    if (! isset($currentChunk['metadata'][$key])) {
                        $currentChunk['metadata'][$key] = $value;
                    }
                }
            } else {
                // Save current chunk and start a new one
                $combinedChunks[] = $currentChunk;
                $currentChunk = $chunk;
            }
        }

        // Don't forget the last chunk
        if ($currentChunk !== null) {
            $combinedChunks[] = $currentChunk;
        }

        return $combinedChunks;
    }
}
