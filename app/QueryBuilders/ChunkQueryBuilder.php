<?php

namespace App\QueryBuilders;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class ChunkQueryBuilder extends Builder
{
    /**
     * Perform VectorChord optimized hybrid search for agent chunks
     */
    public function hybridSearchForAgent(
        int $agentId,
        string $query,
        string $vectorString,
        int $limit,
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

        $sql = "
WITH vectorchord_candidates AS (
    SELECT c.id, c.chunk, c.embedding, c.chunkable_type, c.chunkable_id, c.metadata,
           {$distanceOperator}
    FROM chunks c
    WHERE {$sourceWhereClause}
    ORDER BY ".($quantize ? 'binary_quantize(c.embedding)::bit('.$dims.') <~> binary_quantize(?::vector('.$dims.'))::bit('.$dims.')' : 'c.embedding <-> ?::vector('.$dims.')').'
    LIMIT ?
),
vector_ranked AS (
    SELECT id, chunk, chunkable_type, chunkable_id, metadata, '.($quantize ? 'binary_distance' : 'vector_distance').",
           'vector' as search_type
    FROM vectorchord_candidates
    ORDER BY ".($quantize ? 'binary_distance' : 'vector_distance')."
    LIMIT ?
),
bm25_ranked AS (
    SELECT c.id, c.chunk, c.chunkable_type, c.chunkable_id, c.metadata,
           c.embedding_bm25 <&> bm25_catalog.to_bm25query('chunks_embedding_bm25', tokenizer_catalog.tokenize(?, 'llmlingua2')) AS bm25_rank,
           'bm25' as search_type
    FROM chunks c
    WHERE {$sourceWhereClause}
    ORDER BY bm25_rank
    LIMIT ?
),
combined_results AS (
    SELECT id, chunk, chunkable_type, chunkable_id, metadata, ".($quantize ? 'binary_distance' : 'vector_distance').' as score, search_type FROM vector_ranked
    UNION ALL
    SELECT id, chunk, chunkable_type, chunkable_id, metadata, bm25_rank as score, search_type FROM bm25_ranked
)
SELECT DISTINCT ON (id) id, chunk, chunkable_type, chunkable_id, metadata, search_type, score
FROM combined_results
ORDER BY id, score
LIMIT ?
';

        $params = array_merge(
            [$vectorString],
            $sourceParams,
            [$vectorString],
            [$limit],
            [$limit],
            [$query],
            $sourceParams,
            [$limit],
            [$limit * 2]
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
