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
     * Hybrid search for an agent. Fuses four channels with Reciprocal Rank
     * Fusion (k=60): dense + dense-on-questions + BM25 + BM25-on-questions.
     *
     * Each channel runs as a self-contained subquery `FROM chunks c` with the
     * agent-source filter inlined as `EXISTS (...)`. vchordrq's `prefilter`
     * mode then accelerates the ORDER BY through the index. The previous
     * `candidate_chunks` CTE made the rankers read from an opaque intermediate
     * and bypassed every vector/BM25 index — leaving recall to brute force.
     */
    public function hybridSearchForAgent(
        int $agentId,
        string $query,
        string $vectorString,
        int $limit = 4,
    ): array {
        $dims = 1024;
        $candidateLimit = $limit * 3;

        $sql = <<<SQL
WITH vector_ranked AS (
    SELECT t.id, ROW_NUMBER() OVER (ORDER BY t.dist) AS rank, 'vector' AS search_type
    FROM (
        SELECT c.id, c.embedding <-> ?::vector({$dims}) AS dist
        FROM chunks c
        WHERE EXISTS (
            SELECT 1 FROM agent_sources s
            WHERE s.agent_id = ?
              AND s.sourceable_id = c.chunkable_id
              AND s.sourceable_type = c.chunkable_type
        )
        ORDER BY dist
        LIMIT ?
    ) t
),
question_ranked AS (
    SELECT t.id, ROW_NUMBER() OVER (ORDER BY t.dist) AS rank, 'question' AS search_type
    FROM (
        SELECT c.id, c.questions_embedding <-> ?::vector({$dims}) AS dist
        FROM chunks c
        WHERE c.questions_embedding IS NOT NULL
          AND EXISTS (
              SELECT 1 FROM agent_sources s
              WHERE s.agent_id = ?
                AND s.sourceable_id = c.chunkable_id
                AND s.sourceable_type = c.chunkable_type
          )
        ORDER BY dist
        LIMIT ?
    ) t
),
bm25_ranked AS (
    SELECT t.id, ROW_NUMBER() OVER (ORDER BY t.dist) AS rank, 'bm25' AS search_type
    FROM (
        SELECT c.id, c.embedding_bm25 <&> bm25_catalog.to_bm25query(
            'chunks_embedding_bm25',
            tokenizer_catalog.tokenize(?, 'llmlingua2')
        ) AS dist
        FROM chunks c
        WHERE EXISTS (
            SELECT 1 FROM agent_sources s
            WHERE s.agent_id = ?
              AND s.sourceable_id = c.chunkable_id
              AND s.sourceable_type = c.chunkable_type
        )
        ORDER BY dist
        LIMIT ?
    ) t
),
question_bm25_ranked AS (
    SELECT t.id, ROW_NUMBER() OVER (ORDER BY t.dist) AS rank, 'question_bm25' AS search_type
    FROM (
        SELECT c.id, c.questions_embedding_bm25 <&> bm25_catalog.to_bm25query(
            'chunks_questions_embedding_bm25',
            tokenizer_catalog.tokenize(?, 'llmlingua2')
        ) AS dist
        FROM chunks c
        WHERE c.questions_embedding_bm25 IS NOT NULL
          AND EXISTS (
              SELECT 1 FROM agent_sources s
              WHERE s.agent_id = ?
                AND s.sourceable_id = c.chunkable_id
                AND s.sourceable_type = c.chunkable_type
          )
        ORDER BY dist
        LIMIT ?
    ) t
),
combined_results AS (
    SELECT id, rank, search_type FROM vector_ranked
    UNION ALL
    SELECT id, rank, search_type FROM question_ranked
    UNION ALL
    SELECT id, rank, search_type FROM bm25_ranked
    UNION ALL
    SELECT id, rank, search_type FROM question_bm25_ranked
),
rrf_scores AS (
    SELECT id,
           STRING_AGG(DISTINCT search_type, '+') AS search_type,
           SUM(1.0 / (60 + rank)) AS rrf_score
    FROM combined_results
    GROUP BY id
)
SELECT c.id, c.external_id, c.chunk, c.chunkable_type, c.chunkable_id, c.metadata,
       r.search_type, r.rrf_score AS score
FROM rrf_scores r
JOIN chunks c USING (id)
ORDER BY r.rrf_score DESC
LIMIT ?
SQL;

        // Param order matches each channel's textual `?` order: the ranking
        // expression (now selected in the inner query, before the WHERE) binds
        // first, then the agent_id in EXISTS, then the per-channel LIMIT.
        $params = [
            $vectorString, $agentId, $candidateLimit,
            $vectorString, $agentId, $candidateLimit,
            $query, $agentId, $candidateLimit,
            $query, $agentId, $candidateLimit,
            $limit,
        ];

        return DB::select($sql, $params);
    }

    /**
     * Set vchordrq runtime params for the current transaction. `SET LOCAL`
     * needs an explicit transaction — otherwise values leak on pooled
     * connections (pgbouncer / Octane).
     */
    public function configureSearchAccuracy(string $accuracyLevel, array $config): void
    {
        $probes = $config['search']['probes'][$accuracyLevel]
            ?? $config['search']['probes']['default']
            ?? 64;

        $epsilon = $config['search']['epsilon'][$accuracyLevel]
            ?? $config['search']['epsilon']['default']
            ?? 1.9;

        DB::statement("SET LOCAL vchordrq.probes = {$probes}");
        // prefilter is a database-wide default (vchordrq install migration).
        DB::statement("SET LOCAL vchordrq.epsilon = {$epsilon}");
    }
}
