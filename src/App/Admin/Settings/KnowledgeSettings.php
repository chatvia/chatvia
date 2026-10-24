<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;

/**
 * What agents learn from, and how they search it, edited on three pages.
 * Settings → Knowledge: the file types workspaces may upload, website
 * import (Firecrawl), the AI enhancement of chunks. Settings → Splitting: how text
 * is split into chunks (unit, size, overlap, smallest chunk, the strategy for each kind of
 * content, the tokenizer). Settings → Search: hybrid search (keywords next
 * to meaning), the results an agent gets, follow-up questions, and the
 * minimum scores. A blank value keeps the default from config.
 */
class KnowledgeSettings extends AdminSettings
{
    /** @var list<string>|null KnowledgeFileType values. */
    #[ConfigKey('chatvia.knowledge.file_types')]
    public ?array $file_types = null;

    #[ConfigKey('chatvia.ai.chunker.enhance')]
    public ?bool $enhance_chunks = null;

    #[ConfigKey('chatvia.ai.chunker.enhance_text')]
    public ?bool $enhance_text_chunks = null;

    #[ConfigKey('services.firecrawl.key'), ShouldBeEncrypted]
    public ?string $firecrawl_api_key = null;

    #[ConfigKey('services.firecrawl.endpoint')]
    public ?string $firecrawl_endpoint = null;

    #[ConfigKey('services.firecrawl.rate_per_minute')]
    public ?int $firecrawl_rate_per_minute = null;

    #[ConfigKey('chatvia.ai.chunking.unit')]
    public ?string $chunk_unit = null;

    #[ConfigKey('chatvia.ai.chunking.size')]
    public ?int $chunk_size = null;

    #[ConfigKey('chatvia.ai.chunking.overlap')]
    public ?int $chunk_overlap = null;

    #[ConfigKey('chatvia.ai.chunking.minimum')]
    public ?int $chunk_minimum = null;

    #[ConfigKey('chatvia.ai.chunking.strategies.markdown')]
    public ?string $markdown_strategy = null;

    #[ConfigKey('chatvia.ai.chunking.strategies.text')]
    public ?string $text_strategy = null;

    #[ConfigKey('chatvia.ai.chunking.strategies.transcript')]
    public ?string $transcript_strategy = null;

    #[ConfigKey('chatvia.ai.tokenizer')]
    public ?string $tokenizer = null;

    #[ConfigKey('chatvia.vector.search.hybrid')]
    public ?bool $hybrid_search = null;

    #[ConfigKey('chatvia.vector.search.mode')]
    public ?string $search_mode = null;

    #[ConfigKey('chatvia.vector.search.relevance_check')]
    public ?bool $relevance_check = null;

    #[ConfigKey('chatvia.vector.search.relevance_threshold')]
    public ?float $relevance_threshold = null;

    #[ConfigKey('chatvia.vector.search.max_chunks')]
    public ?int $max_chunks = null;

    #[ConfigKey('chatvia.vector.search.max_context_tokens')]
    public ?int $max_context_tokens = null;

    #[ConfigKey('chatvia.vector.search.similarity_threshold')]
    public ?float $similarity_threshold = null;

    #[ConfigKey('chatvia.vector.search.qa_similarity_threshold')]
    public ?float $qa_similarity_threshold = null;

    #[ConfigKey('chatvia.ai.query.rewriter.enabled')]
    public ?bool $query_rewriter = null;

    public static function group(): string
    {
        return 'knowledge';
    }
}
