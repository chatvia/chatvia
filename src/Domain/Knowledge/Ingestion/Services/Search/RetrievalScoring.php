<?php

namespace Domain\Knowledge\Ingestion\Services\Search;

/**
 * Pure scoring + text-cleaning helpers for RAG retrieval results. Extracted from
 * {@see KnowledgeBase} so the rank→confidence math and XML stripping — which
 * shape every answer the agent gives — are unit-testable without standing up the
 * pgvector hybrid-search path.
 */
class RetrievalScoring
{
    /**
     * Convert a 0-based RRF rank into a display confidence: the top result maps
     * to ~0.99 and the last to ~0.50, linearly. A lone (or single) result is
     * always 0.99.
     */
    public static function confidenceScore(int $rank, int $total): float
    {
        if ($total <= 1) {
            return 0.99;
        }

        $confidence = 0.99 - (($rank / max($total - 1, 1)) * 0.49);

        return round($confidence, 4);
    }

    /**
     * Strip XML/HTML tags from a chunk before it's shown to the model: paired
     * tags are removed along with their content, self-closing tags are dropped,
     * and leftover whitespace is collapsed.
     */
    public static function stripXmlTags(string $text): string
    {
        // Opening tag + content + closing tag.
        $text = preg_replace('/<[^>]+>.*?<\/[^>]+>/s', '', $text);

        // Self-closing tags.
        $text = preg_replace('/<[^>]+\/>/s', '', $text);

        // Collapse whitespace left behind.
        $text = preg_replace('/\s+/', ' ', $text);

        return trim($text);
    }
}
