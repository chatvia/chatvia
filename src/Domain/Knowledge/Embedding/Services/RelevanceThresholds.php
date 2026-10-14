<?php

declare(strict_types=1);

namespace Domain\Knowledge\Embedding\Services;

use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Embedding\Enums\EmbeddingProvider;
use Domain\Knowledge\Embedding\Rerank\RerankModelCatalog;

/**
 * The bars of the relevance checks, for knowledge chunks and for tools: the
 * value set in config (the admin panel or .env) when there is one, else the
 * measured value of the model in use — the reranking model's
 * ({@see RerankModelCatalog}), or, for the basic similarity check without a
 * reranker, the embedding model's ({@see EmbeddingProvider}); and the bar of
 * a Q&A answer matched by meaning. A similarity bar is null when the
 * embedding model was never measured: then nothing is gated (and Q&A answers
 * match only as a whole question).
 */
final class RelevanceThresholds
{
    public static function knowledgeRerank(Reranker $reranker): float
    {
        return self::configured('chatvia.vector.search.relevance_threshold')
            ?? RerankModelCatalog::thresholds($reranker->model())->knowledge;
    }

    public static function knowledgeSimilarity(): ?float
    {
        return self::configured('chatvia.vector.search.similarity_threshold')
            ?? EmbeddingProvider::configured()?->similarityThresholds()?->knowledge;
    }

    public static function toolRerank(Reranker $reranker, bool $mutates): float
    {
        $thresholds = RerankModelCatalog::thresholds($reranker->model());

        return $mutates
            ? self::configured('chatvia.agent.tools.routing.relevance_threshold_write') ?? $thresholds->toolWrite
            : self::configured('chatvia.agent.tools.routing.relevance_threshold_read') ?? $thresholds->toolRead;
    }

    public static function toolSimilarity(bool $mutates): ?float
    {
        $thresholds = EmbeddingProvider::configured()?->similarityThresholds();

        return $mutates
            ? self::configured('chatvia.agent.tools.routing.similarity_threshold_write') ?? $thresholds?->toolWrite
            : self::configured('chatvia.agent.tools.routing.similarity_threshold_read') ?? $thresholds?->toolRead;
    }

    public static function qaSimilarity(): ?float
    {
        return self::configured('chatvia.vector.search.qa_similarity_threshold')
            ?? EmbeddingProvider::configured()?->qaSimilarityThreshold();
    }

    /** A number set in config, or null when the key is empty. */
    private static function configured(string $key): ?float
    {
        $value = config($key);

        return is_numeric($value) ? (float) $value : null;
    }
}
