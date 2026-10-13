<?php

declare(strict_types=1);

namespace App\Admin\Settings;

/**
 * How agents search their knowledge sources for the parts that answer a
 * question: keyword search next to meaning, the search mode, the relevance
 * check and its minimum, how many results an agent gets, and whether
 * follow-up questions are rewritten first.
 */
class SearchSettings extends AdminSettings
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

    public static function group(): string
    {
        return 'search';
    }
}
