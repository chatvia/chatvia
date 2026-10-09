<?php

namespace Domain\Knowledge\Retrieval\Queries;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Retrieval\Data\RetrievedChunkData;
use Illuminate\Support\Facades\DB;

/**
 * The SQL of the knowledge-base search: four ranked channels over an agent's
 * chunks — dense vector, dense vector on the generated questions, BM25, and
 * BM25 on the questions — fused with Reciprocal Rank Fusion (k = 60).
 *
 * Each channel is a self-contained subquery `FROM chunks c` with the agent's
 * sources inlined as an `EXISTS (...)` filter, so vchordrq's `prefilter` mode
 * can serve the ORDER BY from the vector/BM25 index. A shared candidate CTE
 * would hide the indexes and leave recall to a brute-force scan.
 *
 * Every channel also carries the scalar `c.user_id = ?` tenant filter: the
 * EXISTS alone is a correlated subplan the prefilter re-probes per visited
 * row, so without it the scan cost (and, once the probed lists run dry, the
 * recall) grows with ALL platform data instead of the workspace's own.
 * `user_id` is btree-indexed, which also gives small tenants a cheap path.
 *
 * Retrieval quality is measured by an evaluation harness: any change to this
 * SQL changes search results and must be measured first.
 */
final class HybridChunkSearch
{
    /** Stored embedding size (pgvector halfvec). */
    private const int DIMENSIONS = 1024;

    /** Each channel returns this many times the final limit before fusion. */
    private const int CANDIDATES_PER_RESULT = 3;

    /**
     * @param  string  $queryVector  the query embedding as a pgvector literal (`[0.1,0.2,…]`)
     * @return list<RetrievedChunkData> best fused score first
     */
    public function search(Agent $agent, string $query, string $queryVector, int $limit = 4): array
    {
        $agentId = $agent->id;
        $workspaceId = (int) $agent->user_id;
        $candidateLimit = $limit * self::CANDIDATES_PER_RESULT;

        // Bindings follow each channel's textual `?` order: the ranking
        // expression, the tenant filter, the agent id in EXISTS and the
        // channel limit; the final LIMIT comes last.
        $bindings = [
            $queryVector, $workspaceId, $agentId, $candidateLimit,
            $queryVector, $workspaceId, $agentId, $candidateLimit,
            $query, $workspaceId, $agentId, $candidateLimit,
            $query, $workspaceId, $agentId, $candidateLimit,
            $limit,
        ];

        return array_map(RetrievedChunkData::fromRow(...), DB::select($this->sql(), $bindings));
    }

    /**
     * Set the vchordrq search parameters for the current transaction. `SET
     * LOCAL` needs an explicit transaction — otherwise the values leak to the
     * next user of a pooled connection.
     *
     * @param  array<string, mixed>  $config  the `chatvia.vector` config
     */
    public function configureAccuracy(string $accuracyLevel, array $config): void
    {
        // SET takes no bind parameters, so the values are interpolated; the
        // casts keep a stray config/env string from becoming SQL.
        $probes = max(1, (int) ($config['search']['probes'][$accuracyLevel]
            ?? $config['search']['probes']['default']
            ?? 64));

        $epsilon = (float) ($config['search']['epsilon'][$accuracyLevel]
            ?? $config['search']['epsilon']['default']
            ?? 1.9);

        DB::statement("SET LOCAL vchordrq.probes = {$probes}");
        // prefilter is a database-wide default (vchordrq install migration).
        DB::statement("SET LOCAL vchordrq.epsilon = {$epsilon}");
    }

    private function sql(): string
    {
        $dims = self::DIMENSIONS;

        $channels = implode(",\n", [
            $this->channel('vector_ranked', 'vector', "c.embedding <-> ?::halfvec({$dims})"),
            $this->channel('question_ranked', 'question', "c.questions_embedding <-> ?::halfvec({$dims})", 'c.questions_embedding IS NOT NULL'),
            $this->channel('bm25_ranked', 'bm25', $this->bm25Distance('c.embedding_bm25', 'chunks_embedding_bm25')),
            $this->channel('question_bm25_ranked', 'question_bm25', $this->bm25Distance('c.questions_embedding_bm25', 'chunks_questions_embedding_bm25'), 'c.questions_embedding_bm25 IS NOT NULL'),
        ]);

        return <<<SQL
WITH {$channels},
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
SELECT c.id, c.external_id, c.chunk, c.chunk_index, c.chunkable_type, c.chunkable_id, c.metadata,
       r.search_type, r.rrf_score AS score
FROM rrf_scores r
JOIN chunks c USING (id)
ORDER BY r.rrf_score DESC
LIMIT ?
SQL;
    }

    /**
     * One ranked channel: the agent's closest chunks by `$distance`, numbered
     * by rank.
     */
    private function channel(string $name, string $searchType, string $distance, ?string $requiredColumn = null): string
    {
        $required = $requiredColumn !== null ? "\n          AND {$requiredColumn}" : '';

        return <<<SQL
{$name} AS (
    SELECT t.id, ROW_NUMBER() OVER (ORDER BY t.dist) AS rank, '{$searchType}' AS search_type
    FROM (
        SELECT c.id, {$distance} AS dist
        FROM chunks c
        WHERE c.user_id = ?{$required}
          AND EXISTS (
              SELECT 1 FROM agent_sources s
              WHERE s.agent_id = ?
                AND s.sourceable_id = c.chunkable_id
                AND s.sourceable_type = c.chunkable_type
          )
        ORDER BY dist
        LIMIT ?
    ) t
)
SQL;
    }

    private function bm25Distance(string $column, string $index): string
    {
        return <<<SQL
{$column} <&> bm25_catalog.to_bm25query(
            '{$index}',
            tokenizer_catalog.tokenize(?, 'llmlingua2')
        )
SQL;
    }
}
