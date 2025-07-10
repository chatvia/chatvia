<?php

namespace App\Support;

use App\Data\Chunk;

class AzureMarkdownSplitter
{
    private const MIN_CHUNK_SIZE = 200;
    private const MAJOR_HEADING_MAX_LEVEL = 3;
    private const PAGE_NUMBER_PATTERN = '/<!-- PageNumber="([^"]+)"/';
    private const HEADER_PATTERN = '/^(#+)\s*(.+)/';

    // Page-related comment markers
    private const PAGE_MARKERS = [
        'PageNumber=',
        'PageBreak',
        'PageFooter',
        'PageHeader'
    ];

    public function split(string $content): array
    {
        $lines = explode("\n", $content);

        $chunks = [];
        $currentChunk = null;
        $currentPage = null; // Start with null until we find the first page number

        foreach ($lines as $line) {
            $line = trim($line);

            if ($this->shouldSkipLine($line)) {
                continue;
            }

            $pageNumber = $this->extractPageNumber($line);
            if ($pageNumber !== null) {
                $currentPage = $pageNumber;
                continue;
            }

            if ($this->isPageRelatedComment($line)) {
                continue;
            }

            // Skip content if we haven't found a page number yet
            if ($currentPage === null) {
                continue;
            }

            $headerInfo = $this->parseHeader($line);
            if ($headerInfo !== null) {
                $currentChunk = $this->handleHeader(
                    $headerInfo,
                    $currentChunk,
                    $chunks,
                    $currentPage
                );
            } else {
                $currentChunk = $this->handleRegularContent(
                    $line,
                    $currentChunk,
                    $chunks,
                    $currentPage
                );
            }
        }

        // Finalize processing
        if ($currentChunk && $this->hasValidContent($currentChunk)) {
            $chunks[] = $currentChunk;
        }

        return $this->postProcessChunks($chunks);
    }

    /**
     * Check if a line should be skipped during processing.
     */
    private function shouldSkipLine(string $line): bool
    {
        if (empty($line)) {
            return true;
        }

        // Skip HTML comments that aren't page markers
        if (str_starts_with($line, '<!--')) {
            foreach (self::PAGE_MARKERS as $marker) {
                if (str_contains($line, $marker)) {
                    return false;
                }
            }
            return true;
        }

        return false;
    }

    /**
     * Extract page number from a page number comment.
     */
    private function extractPageNumber(string $line): ?string
    {
        if (!str_contains($line, 'PageNumber=')) {
            return null;
        }

        preg_match(self::PAGE_NUMBER_PATTERN, $line, $matches);
        return $matches ? $matches[1] : null;
    }

    /**
     * Check if line is a page-related comment that should be skipped.
     */
    private function isPageRelatedComment(string $line): bool
    {
        $pageComments = ['PageBreak', 'PageFooter', 'PageHeader'];

        foreach ($pageComments as $comment) {
            if (str_contains($line, "<!-- $comment")) {
                return true;
            }
        }

        return false;
    }

    /**
     * Parse header information from a line.
     *
     * @return array{level: int, heading: string}|null
     */
    private function parseHeader(string $line): ?array
    {
        if (!preg_match(self::HEADER_PATTERN, $line, $matches)) {
            return null;
        }

        return [
            'level' => strlen($matches[1]),
            'heading' => trim($matches[2])
        ];
    }

    /**
     * Handle header lines and create new chunks as needed.
     */
    private function handleHeader(
        array $headerInfo,
        ?Chunk $currentChunk,
        array &$chunks,
        string $currentPage
    ): ?Chunk {
        $level = $headerInfo['level'];
        $heading = $headerInfo['heading'];

        if ($level <= self::MAJOR_HEADING_MAX_LEVEL) {
            return $this->createNewChunkForMajorHeader(
                $currentChunk,
                $chunks,
                $level,
                $heading,
                $currentPage
            );
        }

        return $this->addMinorHeaderToCurrentChunk(
            $currentChunk,
            $chunks,
            $level,
            $heading,
            $currentPage
        );
    }

    /**
     * Create a new chunk for major headers (levels 1-3).
     */
    private function createNewChunkForMajorHeader(
        ?Chunk $currentChunk,
        array &$chunks,
        int $level,
        string $heading,
        string $currentPage
    ): Chunk {
        // Save previous chunk if it has valid content
        if ($currentChunk && $this->hasValidContent($currentChunk)) {
            $chunks[] = $currentChunk;
        }

        // Format heading as markdown and include it in content only
        $headerLine = str_repeat('#', $level) . ' ' . $heading;

        return $this->createChunk(
            level: $level,
            heading: $heading, // Store heading separately as required
            content: $headerLine,
            pages: [$currentPage],
            chunkId: count($chunks) + 1
        );
    }

    /**
     * Add minor header (level 4+) to current chunk as content.
     */
    private function addMinorHeaderToCurrentChunk(
        ?Chunk $currentChunk,
        array &$chunks,
        int $level,
        string $heading,
        string $currentPage
    ): Chunk {
        $headerLine = str_repeat('#', $level) . ' ' . $heading;

        if ($currentChunk) {
            $this->appendContentToChunk($currentChunk, $headerLine, $currentPage);
            return $currentChunk;
        }

        // Create new chunk for orphaned minor header
        return $this->createChunk(
            level: 0,
            heading: '', // Don't store heading separately
            content: $headerLine,
            pages: [$currentPage],
            chunkId: count($chunks) + 1
        );
    }

    /**
     * Handle regular content lines.
     */
    private function handleRegularContent(
        string $line,
        ?Chunk $currentChunk,
        array &$chunks,
        string $currentPage
    ): Chunk {
        if ($currentChunk) {
            $this->appendContentToChunk($currentChunk, $line, $currentPage);
            return $currentChunk;
        }

        // Create chunk for content before any heading
        return $this->createChunk(
            level: 0,
            heading: '', // Don't store heading separately
            content: $line,
            pages: [$currentPage],
            chunkId: count($chunks) + 1
        );
    }

    /**
     * Create a new chunk with given parameters.
     */
    private function createChunk(
        int $level,
        string $heading,
        string $content,
        array $pages,
        int $chunkId
    ): Chunk {
        // Sort pages as strings to maintain order
        sort($pages, SORT_STRING);

        return Chunk::from([
            'level' => $level,
            'heading' => $heading,
            'original_content' => $content,
            'metadata' => ['pages' => $pages],
            'chunk_id' => $chunkId,
            'embedding' => [],
        ]);
    }

    /**
     * Append content to an existing chunk and update page tracking.
     */
    private function appendContentToChunk(Chunk $chunk, string $content, string $currentPage): void
    {
        if (!empty($chunk->original_content)) {
            $chunk->original_content .= "\n";
            if (str_starts_with($content, '#')) {
                $chunk->original_content .= "\n"; // Extra spacing for headers
            }
        }

        $chunk->original_content .= $content;
        $this->addPageToChunk($chunk, $currentPage);
    }

    /**
     * Add page number to chunk metadata if not already present.
     */
    private function addPageToChunk(Chunk $chunk, string $page): void
    {
        // Don't add empty page numbers
        if (empty(trim($page))) {
            return;
        }

        if (!in_array($page, $chunk->metadata['pages'], true)) {
            $chunk->metadata['pages'][] = $page;
            // Sort pages to maintain order (as strings)
            sort($chunk->metadata['pages'], SORT_STRING);
        }
    }

    /**
     * Post-process chunks: merge small ones, filter invalid ones, and re-index.
     */
    private function postProcessChunks(array $chunks): array
    {
        $chunks = $this->mergeSmallChunks($chunks);
        $chunks = $this->filterValidChunks($chunks);

        return $this->reindexChunks($chunks);
    }

    /**
     * Merge chunks that are too small with adjacent chunks.
     */
    private function mergeSmallChunks(array $chunks): array
    {
        $merged = [];

        for ($i = 0; $i < count($chunks); $i++) {
            $chunk = $chunks[$i];

            if (!$this->isChunkTooSmall($chunk)) {
                $merged[] = $chunk;
                continue;
            }

            $mergedSuccessfully = $this->attemptChunkMerge($chunk, $merged, $chunks, $i);

            if (!$mergedSuccessfully) {
                $merged[] = $chunk;
            }
        }

        return $merged;
    }

    /**
     * Check if a chunk is too small and needs merging.
     */
    private function isChunkTooSmall(Chunk $chunk): bool
    {
        return strlen(trim($chunk->original_content)) < self::MIN_CHUNK_SIZE;
    }

    /**
     * Attempt to merge a small chunk with adjacent chunks.
     *
     * @param Chunk $chunk The chunk to merge
     * @param array &$merged Already processed chunks
     * @param array $chunks All chunks being processed
     * @param int &$currentIndex Current index in the chunks array (will be modified if merging with next)
     * @return bool True if merge was successful
     */
    private function attemptChunkMerge(Chunk $chunk, array &$merged, array $chunks, int &$currentIndex): bool
    {
        // Try merging with previous chunk first
        if (!empty($merged)) {
            $this->mergeWithPreviousChunk($chunk, $merged);
            return true;
        }

        // Try merging with next chunk
        if ($currentIndex + 1 < count($chunks)) {
            $nextChunk = $chunks[$currentIndex + 1];
            $mergedChunk = $this->mergeWithNextChunk($chunk, $nextChunk);
            $merged[] = $mergedChunk;
            $currentIndex++; // Skip the next chunk since we merged it
            return true;
        }

        return false;
    }

    /**
     * Merge chunk with the previous chunk in the merged array.
     */
    private function mergeWithPreviousChunk(Chunk $chunk, array &$merged): void
    {
        $prevChunk = &$merged[count($merged) - 1];

        $this->combineChunkContent($prevChunk, $chunk);
        $this->mergeChunkPages($prevChunk, $chunk);
    }

    /**
     * Merge chunk with the next chunk and return the result.
     */
    private function mergeWithNextChunk(Chunk $currentChunk, Chunk $nextChunk): Chunk
    {
        $combinedContent = $this->buildCombinedContent($currentChunk, $nextChunk);

        // Merge and sort pages as strings
        $mergedPages = array_unique(array_merge(
            $currentChunk->metadata['pages'],
            $nextChunk->metadata['pages']
        ));
        sort($mergedPages, SORT_STRING);

        return Chunk::from([
            'level' => $nextChunk->level,
            'heading' => $nextChunk->heading, // Preserve heading from next chunk
            'original_content' => $combinedContent,
            'metadata' => [
                'pages' => $mergedPages
            ],
            'chunk_id' => $nextChunk->chunk_id,
            'embedding' => [],
        ]);
    }

    /**
     * Combine content from two chunks.
     */
    private function combineChunkContent(Chunk $targetChunk, Chunk $sourceChunk): void
    {
        // Only add the original_content since headings are already in it
        if (!empty($sourceChunk->original_content)) {
            if (!empty($targetChunk->original_content)) {
                $targetChunk->original_content .= "\n\n" . $sourceChunk->original_content;
            } else {
                $targetChunk->original_content = $sourceChunk->original_content;
            }
        }
    }

    /**
     * Build combined content from current and next chunks.
     */
    private function buildCombinedContent(Chunk $currentChunk, Chunk $nextChunk): string
    {
        $parts = [];

        // Add original content only (headings are already included)
        if (!empty($currentChunk->original_content)) {
            $parts[] = $currentChunk->original_content;
        }

        if (!empty($nextChunk->original_content)) {
            $parts[] = $nextChunk->original_content;
        }

        return implode("\n\n", array_filter($parts));
    }

    /**
     * Merge page arrays from two chunks.
     */
    private function mergeChunkPages(Chunk $targetChunk, Chunk $sourceChunk): void
    {
        $targetChunk->metadata['pages'] = array_unique(array_merge(
            $targetChunk->metadata['pages'],
            $sourceChunk->metadata['pages']
        ));
        // Sort pages as strings to maintain order
        sort($targetChunk->metadata['pages'], SORT_STRING);
    }

    /**
     * Filter out chunks that don't meet validity criteria.
     */
    private function filterValidChunks(array $chunks): array
    {
        return array_filter($chunks, function($chunk) {
            $content = trim($chunk->original_content);

            return strlen($content) > self::MIN_CHUNK_SIZE;
        });
    }

    /**
     * Re-index chunks and sort their page information.
     */
    private function reindexChunks(array $chunks): array
    {
        $chunks = array_values($chunks);

        foreach ($chunks as $index => $chunk) {
            $chunk->chunk_id = $index + 1;
            // Sort pages as strings to maintain order
            sort($chunk->metadata['pages'], SORT_STRING);
        }

        return $chunks;
    }

    /**
     * Check if a chunk has valid content worth keeping.
     */
    private function hasValidContent(Chunk $chunk): bool
    {
        $content = trim($chunk->original_content);

        // Valid if it has substantial content
        return strlen($content) > self::MIN_CHUNK_SIZE;
    }

    /**
     * Simple function to split markdown content by H1, H2, and H3 headers.
     * Also tracks page numbers from PageNumber comments.
     *
     * @param string $markdownContent The markdown content to split
     * @return array Array of chunks, each containing content under a header
     */
    public function splitByHeader(string $markdownContent): array
    {
        $lines = array_filter(explode("\n", $markdownContent));
        $chunks = [];
        $currentChunk = '';
        $currentHeading = '';
        $currentPage = 1;
        $chunkPages = [];
        $chunkId = 1;

        foreach ($lines as $line) {
            // Check for page number comment
            if (preg_match('/<!-- PageNumber="([^"]+)"/', $line, $pageMatches)) {
                ++$currentPage;
                continue;
            }

            // Check if line is H1, H2, or H3 header
            if (preg_match('/^(#{1,3})\s+(.+)$/', $line, $matches)) {
                // Save previous chunk if it has content
                if (!empty(trim($currentChunk))) {
                    $chunks[] = [
                        'chunk_id' => $chunkId,
                        'heading' => $currentHeading,
                        'content' => trim($currentChunk),
                        'page_numbers' => array_unique($chunkPages),
                    ];
                    $chunkId++;
                }

                // Start new chunk
                $currentHeading = trim($matches[2]);
                $currentChunk = $line . "\n";
                $chunkPages = [$currentPage];
            } else {
                // Add line to current chunk
                $currentChunk .= $line . "\n";

                // Add current page to chunk pages if not already added
                if (!in_array($currentPage, $chunkPages)) {
                    $chunkPages[] = $currentPage;
                }
            }
        }

        // Add the last chunk if it has content
        if (!empty(trim($currentChunk))) {
            $chunks[] = [
                'chunk_id' => $chunkId,
                'heading' => $currentHeading,
                'content' => trim($currentChunk),
                'page_numbers' => array_unique($chunkPages)
            ];
        }

        return $chunks;
    }
}
