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
     * Perform VectorChord optimized hybrid search for agent chunks.
     * Returns the top N (default: 4) chunks fused from four channels with
     * Reciprocal Rank Fusion (k=60):
     *   1. Vector search over chunk embeddings
     *   2. Vector search over chunk questions embeddings (HyDE-style)
     *   3. BM25 over chunk text
     *   4. BM25 over chunk questions text
     *
     * The agent's allowed sources are materialised once into the `allowed` CTE
     * and joined against `chunks` to form `candidate_chunks` — every channel
     * then ranks over the same already-filtered set instead of re-evaluating
     * a 100-way OR per CTE. `SET LOCAL vchordrq.prefilter = on` keeps recall
     * high when the allowed set is selective.
     */
    public function hybridSearchForAgent(
        int $agentId,
        string $query,
        string $vectorString,
        int $limit = 4,
        bool $quantize = true
    ): array {
        $dims = 1024;

        $candidateLimit = $limit * 3;

        $distanceExpr = $quantize
            ? "binary_quantize(c.embedding)::bit({$dims}) <~> binary_quantize(?::vector({$dims}))::bit({$dims})"
            : "c.embedding <-> ?::vector({$dims})";

        $sql = <<<SQL
WITH allowed AS (
    SELECT s.sourceable_id   AS chunkable_id,
           s.sourceable_type  AS chunkable_type
    FROM agent_sources s
    WHERE s.agent_id = ?
),
candidate_chunks AS (
    SELECT c.id, c.external_id, c.chunk, c.embedding, c.questions_embedding,
           c.embedding_bm25, c.questions_embedding_bm25,
           c.chunkable_type, c.chunkable_id, c.metadata
    FROM chunks c
    JOIN allowed a ON a.chunkable_id = c.chunkable_id
                  AND a.chunkable_type = c.chunkable_type
),
vector_ranked AS (
    SELECT id, external_id, chunk, chunkable_type, chunkable_id, metadata,
           ROW_NUMBER() OVER (ORDER BY {$distanceExpr}) AS rank,
           'vector' AS search_type
    FROM candidate_chunks c
    ORDER BY rank
    LIMIT ?
),
question_ranked AS (
    SELECT id, external_id, chunk, chunkable_type, chunkable_id, metadata,
           ROW_NUMBER() OVER (ORDER BY c.questions_embedding <-> ?::vector({$dims})) AS rank,
           'question' AS search_type
    FROM candidate_chunks c
    WHERE c.questions_embedding IS NOT NULL
    ORDER BY rank
    LIMIT ?
),
bm25_ranked AS (
    SELECT id, external_id, chunk, chunkable_type, chunkable_id, metadata,
           ROW_NUMBER() OVER (ORDER BY c.embedding_bm25 <&> bm25_catalog.to_bm25query('chunks_embedding_bm25', tokenizer_catalog.tokenize(?, 'llmlingua2'))) AS rank,
           'bm25' AS search_type
    FROM candidate_chunks c
    ORDER BY rank
    LIMIT ?
),
question_bm25_ranked AS (
    SELECT id, external_id, chunk, chunkable_type, chunkable_id, metadata,
           ROW_NUMBER() OVER (ORDER BY c.questions_embedding_bm25 <&> bm25_catalog.to_bm25query('chunks_questions_embedding_bm25', tokenizer_catalog.tokenize(?, 'llmlingua2'))) AS rank,
           'question_bm25' AS search_type
    FROM candidate_chunks c
    WHERE c.questions_embedding_bm25 IS NOT NULL
    ORDER BY rank
    LIMIT ?
),
combined_results AS (
    SELECT * FROM vector_ranked
    UNION ALL
    SELECT * FROM question_ranked
    UNION ALL
    SELECT * FROM bm25_ranked
    UNION ALL
    SELECT * FROM question_bm25_ranked
),
rrf_scores AS (
    SELECT id, external_id, chunk, chunkable_type, chunkable_id, metadata,
           STRING_AGG(DISTINCT search_type, '+') AS search_type,
           SUM(1.0 / (60 + rank))                AS rrf_score
    FROM combined_results
    GROUP BY id, external_id, chunk, chunkable_type, chunkable_id, metadata
)
SELECT id, external_id, chunk, chunkable_type, chunkable_id, metadata, search_type, rrf_score AS score
FROM rrf_scores
ORDER BY rrf_score DESC
LIMIT ?
SQL;

        $params = [
            $agentId,         // allowed.agent_id
            $vectorString,    // distance expr in vector_ranked
            $candidateLimit,  // vector_ranked LIMIT
            $vectorString,    // question_ranked ORDER BY
            $candidateLimit,  // question_ranked LIMIT
            $query,           // bm25 tokenize
            $candidateLimit,  // bm25_ranked LIMIT
            $query,           // question bm25 tokenize
            $candidateLimit,  // question_bm25_ranked LIMIT
            $limit,           // final LIMIT
        ];

        return DB::select($sql, $params);
    }

    /**
     * Configure VectorChord search parameters for accuracy level. Uses
     * `SET LOCAL`, so the caller must run this inside a transaction —
     * otherwise the values leak to the next request on a pooled connection.
     */
    public function configureSearchAccuracy(string $accuracyLevel, array $config): void
    {
        $probes = $config['search']['probes'][$accuracyLevel]
            ?? $config['search']['probes']['default']
            ?? 20;

        DB::statement("SET LOCAL vchordrq.probes = {$probes}");
        DB::statement('SET LOCAL vchordrq.prefilter = on');
        DB::statement('SET LOCAL vchordrq.epsilon = 1.9');
    }
}
