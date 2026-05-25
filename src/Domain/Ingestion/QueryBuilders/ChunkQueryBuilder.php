<?php

namespace Domain\Ingestion\QueryBuilders;

use Domain\Ingestion\Models\Chunk;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * @template TModel of Chunk
 *
 * @extends Builder<TModel>
 */
class ChunkQueryBuilder extends Builder
{
    /**
     * Perform VectorChord optimized hybrid search for agent chunks
     * Returns only the top N highest confidence chunks (default: 4)
     *
     * Three retrieval sources are fused with Reciprocal Rank Fusion (k=60):
     *   1. Vector search over chunk embeddings
     *   2. Vector search over chunk_questions (best-matching question per chunk)
     *   3. BM25 over chunk text
     */
    public function hybridSearchForAgent(
        int $agentId,
        string $query,
        string $vectorString,
        int $limit = 4,
        bool $quantize = true
    ): array {
        $sources = DB::table('agent_sources')
            ->where('agent_id', $agentId)
            ->select(['sourceable_id', 'sourceable_type'])
            ->get();

        if ($sources->isEmpty()) {
            return [];
        }

        $sourceConditions = [];
        $sourceParams = [];

        foreach ($sources as $source) {
            $sourceConditions[] = '(c.chunkable_id = ? AND c.chunkable_type = ?)';
            $sourceParams[] = $source->sourceable_id;
            $sourceParams[] = $source->sourceable_type;
        }

        $sourceWhereClause = '('.implode(' OR ', $sourceConditions).')';
        $dims = 1024;

        $distanceOperator = $quantize
            ? 'binary_quantize(c.embedding)::bit('.$dims.') <~> binary_quantize(?::vector('.$dims.'))::bit('.$dims.') as binary_distance'
            : 'c.embedding <-> ?::vector('.$dims.') as vector_distance';

        // Get slightly more candidates to ensure good hybrid results
        $candidateLimit = $limit * 3;

        // Over-fetch questions before dedup so we still end up with ~candidateLimit
        // unique chunks (questions are ~6 per chunk on average).
        $questionPoolLimit = $candidateLimit * 6;

        $vectorOrderField = $quantize ? 'binary_distance' : 'vector_distance';
        $vectorOrderBy = $quantize
            ? "binary_quantize(c.embedding)::bit($dims) <~> binary_quantize(?::vector($dims))::bit($dims)"
            : "c.embedding <-> ?::vector($dims)";

        $sql = "
WITH vectorchord_candidates AS (
    SELECT c.id, c.external_id, c.chunk, c.embedding, c.chunkable_type, c.chunkable_id, c.metadata,
           {$distanceOperator}
    FROM chunks c
    WHERE {$sourceWhereClause}
    ORDER BY {$vectorOrderBy}
    LIMIT ?
),
vector_ranked AS (
    SELECT id, external_id, chunk, chunkable_type, chunkable_id, metadata,
           ROW_NUMBER() OVER (ORDER BY {$vectorOrderField} ASC) as rank,
           'vector' as search_type
    FROM vectorchord_candidates
    LIMIT ?
),
question_pool AS (
    SELECT q.chunk_id, q.embedding <-> ?::vector({$dims}) AS qdist
    FROM chunk_questions q
    JOIN chunks c ON c.id = q.chunk_id
    WHERE {$sourceWhereClause}
    ORDER BY q.embedding <-> ?::vector({$dims}) ASC
    LIMIT ?
),
question_deduped AS (
    SELECT DISTINCT ON (chunk_id) chunk_id, qdist
    FROM question_pool
    ORDER BY chunk_id, qdist ASC
),
question_ranked AS (
    SELECT c.id, c.external_id, c.chunk, c.chunkable_type, c.chunkable_id, c.metadata,
           ROW_NUMBER() OVER (ORDER BY qd.qdist ASC) as rank,
           'question' as search_type
    FROM question_deduped qd
    JOIN chunks c ON c.id = qd.chunk_id
    ORDER BY qd.qdist ASC
    LIMIT ?
),
bm25_ranked AS (
    SELECT c.id, c.external_id, c.chunk, c.chunkable_type, c.chunkable_id, c.metadata,
           ROW_NUMBER() OVER (ORDER BY c.embedding_bm25 <&> bm25_catalog.to_bm25query('chunks_embedding_bm25', tokenizer_catalog.tokenize(?, 'llmlingua2'))) as rank,
           'bm25' as search_type
    FROM chunks c
    WHERE {$sourceWhereClause}
    ORDER BY rank
    LIMIT ?
),
combined_results AS (
    SELECT id, external_id, chunk, chunkable_type, chunkable_id, metadata, rank, search_type FROM vector_ranked
    UNION ALL
    SELECT id, external_id, chunk, chunkable_type, chunkable_id, metadata, rank, search_type FROM question_ranked
    UNION ALL
    SELECT id, external_id, chunk, chunkable_type, chunkable_id, metadata, rank, search_type FROM bm25_ranked
),
rrf_scores AS (
    SELECT id, external_id, chunk, chunkable_type, chunkable_id, metadata,
           STRING_AGG(DISTINCT search_type, '+') as search_type,
           SUM(1.0 / (60 + rank)) as rrf_score
    FROM combined_results
    GROUP BY id, external_id, chunk, chunkable_type, chunkable_id, metadata
)
SELECT id, external_id, chunk, chunkable_type, chunkable_id, metadata, search_type, rrf_score as score
FROM rrf_scores
ORDER BY rrf_score DESC
LIMIT ?
";

        $params = array_merge(
            [$vectorString],                  // distanceOperator in candidates SELECT
            $sourceParams,                    // sourceWhereClause for chunks
            [$vectorString],                  // vectorOrderBy
            [$candidateLimit],                // vectorchord_candidates LIMIT
            [$candidateLimit],                // vector_ranked LIMIT
            [$vectorString],                  // question_pool qdist SELECT
            $sourceParams,                    // sourceWhereClause for chunks (join)
            [$vectorString],                  // question_pool ORDER BY
            [$questionPoolLimit],             // question_pool LIMIT
            [$candidateLimit],                // question_ranked LIMIT
            [$query],                         // bm25 tokenize
            $sourceParams,                    // sourceWhereClause for chunks
            [$candidateLimit],                // bm25_ranked LIMIT
            [$limit]                          // final LIMIT
        );

        return DB::select($sql, $params);
    }

    /**
     * Configure VectorChord search parameters for accuracy level
     */
    public function configureSearchAccuracy(string $accuracyLevel, array $config): void
    {
        $probes = $config['search']['probes'][$accuracyLevel]
            ?? $config['search']['probes']['default']
            ?? 20;

        // Set VectorChord runtime parameters for IVF index probing
        DB::statement("SET vchordrq.probes = {$probes}");
    }
}
