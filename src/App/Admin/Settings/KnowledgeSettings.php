<?php

declare(strict_types=1);

namespace App\Admin\Settings;

use Spatie\LaravelSettings\Attributes\ShouldBeEncrypted;

/**
 * How agents search their knowledge sources, the optional AI steps of
 * ingestion, and the service that imports websites (Firecrawl). How text is
 * split into chunks is in {@see ChunkingSettings}.
 */
class KnowledgeSettings extends AdminSettings
{
    #[ConfigKey('chatvia.vector.search.hybrid')]
    public ?bool $hybrid_search = null;

    #[ConfigKey('chatvia.vector.search.mode')]
    public ?string $search_mode = null;

    #[ConfigKey('chatvia.vector.search.rerank')]
    public ?bool $rerank = null;

    #[ConfigKey('chatvia.vector.search.relevance_threshold')]
    public ?float $relevance_threshold = null;

    #[ConfigKey('chatvia.vector.search.max_chunks')]
    public ?int $max_chunks = null;

    #[ConfigKey('chatvia.ai.query.rewriter.enabled')]
    public ?bool $query_rewriter = null;

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

    public static function group(): string
    {
        return 'knowledge';
    }
}
