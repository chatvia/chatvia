<?php

namespace Domain\Knowledge\Retrieval\Services;

use Illuminate\Support\Str;

/**
 * Pure text-cleaning helpers for RAG retrieval results. Extracted from
 * {@see KnowledgeBase} so the clean-up of chunk text — which shapes every
 * answer the agent gives — is unit-testable without standing up the pgvector
 * hybrid-search path.
 */
class RetrievalScoring
{
    /**
     * The chunk as the model reads it: HTML or XML tag markup is removed but
     * the text inside it stays, a line break tag becomes a new line, and the
     * text keeps its own line breaks (Markdown headings, lists and tables need
     * them). Three or more line breaks fold to one blank line. Only real tags
     * go: "a < b" and autolinks like <https://example.com> stay.
     */
    public static function plainText(string $text): string
    {
        return Str::of($text)
            ->replaceMatches('/<br\s*\/?>/i', "\n")
            ->replaceMatches('/<\/?[a-zA-Z][a-zA-Z0-9-]*(?:\s[^<>]*)?\/?>/', '')
            ->replaceMatches('/[ \t]+\n/', "\n")
            ->replaceMatches('/\n{3,}/', "\n\n")
            ->trim()
            ->toString();
    }
}
