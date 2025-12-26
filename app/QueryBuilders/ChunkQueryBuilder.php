<?php

namespace App\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ChunkQueryBuilder extends Builder
{
    /**
     * Perform VectorChord optimized hybrid search for agent chunks
     * Returns only the top N highest confidence chunks (default: 4)
     */
    public function hybridSearchForAgent(
        int $agentId,
        string $query,
        string $vectorString,
        int $limit = 4,
        bool $quantize = true
    ): array {
        // First, get all source IDs for this agent from agent_sources
        $sources = DB::table('agent_sources')
            ->where('agent_id', $agentId)
            ->select(['sourceable_id', 'sourceable_type'])
            ->get();

        if ($sources->isEmpty()) {
            return [];
        }

        // Build WHERE clause for chunks based on sources
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
            [$vectorString],
            $sourceParams,
            [$vectorString],
            [$candidateLimit],
            [$candidateLimit],
            [$query],
            $sourceParams,
            [$candidateLimit],
            [$limit]
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
