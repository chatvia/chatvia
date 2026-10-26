<?php

namespace Domain\Knowledge\Retrieval\Queries;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Enums\VectorIndex;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;
use Domain\Knowledge\Retrieval\Data\RetrievedChunkData;
use Domain\Knowledge\Retrieval\Enums\ChunkSearchScope;
use Domain\Knowledge\Retrieval\Services\ChunkVectorIndex;
use Domain\Knowledge\Retrieval\Services\LexicalSearch;
use Illuminate\Support\Facades\DB;

/**
 * The SQL of the knowledge-base search: three ranked channels over an agent's
 * chunks — dense vector, dense vector on the generated questions, and keyword
 * (LexicalSearch) — fused with Reciprocal Rank Fusion (k = 60). With
 * `chatvia.vector.search.hybrid` off, only the two vector channels run. The
 * keyword channel only ranks chunks that share a term with the query.
 *
 * There is no keyword channel on the generated questions: every chunk has
 * them and they share their wording ("What…", "How…", the product's name), so
 * that channel ranked every chunk on weak matches and its vote pushed the
 * right chunks down (the eval's channel weights).
 *
 * The channels reach the agent's chunks in one of two ways
 * ({@see ChunkSearchScope}), chosen from a bounded count of those chunks:
 *
 * - Exact (the agent reaches few chunks): one MATERIALIZED pass over exactly
 *   the agent's chunks scores every channel at once. Exact distances mean
 *   full recall, and no other workspace's rows are ever read, so the cost
 *   follows the agent's size, not the platform's.
 * - Indexed (many chunks): each channel is a self-contained subquery served by
 *   its own index — HNSW for the dense channels (iterative scan keeps
 *   fetching until enough rows pass the tenant and source filters), the
 *   lexical index for the keyword channel. Over binary HNSW indexes
 *   (ChunkVectorIndex) a dense channel re-scores its candidates by the
 *   stored vectors.
 *
 * Retrieval quality is measured by the knowledge retrieval eval
 * (tests/Eval/KnowledgeRetrievalEvalTest.php): any change to this SQL changes
 * search results and must be measured first.
 */
final readonly class HybridChunkSearch
{
    /** Each channel returns this many times the final limit before fusion. */
    private const int CANDIDATES_PER_RESULT = 3;

    public function __construct(
        private EmbeddingStorage $storage,
        private LexicalSearch $lexical,
        private ChunkVectorIndex $index,
    ) {}

    /**
     * How to reach the agent's chunks. Counts them only up to one past the
     * exact-search limit, so the count costs the same for a huge agent as for
     * one at the limit. The tenant filter is left to the search itself.
     *
     * The LATERAL subquery (its LIMIT keeps it from being flattened) makes the
     * walk start at the agent's sources and step into `chunks_page_idx` per
     * source; as a plain join under a LIMIT, the planner may instead walk the
     * whole chunks index hoping to meet the agent's rows early.
     */
    public function scope(Agent $agent, int $exactMaxChunks): ChunkSearchScope
    {
        $cap = max(0, $exactMaxChunks) + 1;

        $reachable = (int) DB::scalar(<<<'SQL'
SELECT count(*) FROM (
    SELECT 1
    FROM agent_sources s
    CROSS JOIN LATERAL (
        SELECT 1
        FROM chunks c
        WHERE c.chunkable_type = s.sourceable_type
          AND c.chunkable_id = s.sourceable_id
        LIMIT ?
    ) c
    WHERE s.agent_id = ?
    LIMIT ?
) reachable
SQL, [$cap, $agent->id, $cap]);

        return match (true) {
            $reachable === 0 => ChunkSearchScope::None,
            $reachable <= $exactMaxChunks => ChunkSearchScope::Exact,
            default => ChunkSearchScope::Indexed,
        };
    }

    /**
     * @param  string  $queryVector  the query embedding as a pgvector literal (`[0.1,0.2,…]`)
     * @param  string  $accuracy  the search mode (`default`, …); tunes the indexed path only
     * @return list<RetrievedChunkData> best fused score first
     */
    public function search(Agent $agent, string $query, string $queryVector, int $limit, ChunkSearchScope $scope, string $accuracy = 'default'): array
    {
        return match ($scope) {
            ChunkSearchScope::None => [],
            ChunkSearchScope::Exact => $this->exact($agent, $query, $queryVector, $limit),
            // SET LOCAL needs an explicit transaction — otherwise the values
            // leak to the next user of a pooled connection.
            ChunkSearchScope::Indexed => DB::transaction(function () use ($agent, $query, $queryVector, $limit, $accuracy): array {
                $this->configureAccuracy($accuracy, (array) config('chatvia.vector', []));

                return $this->indexed($agent, $query, $queryVector, $limit);
            }),
        };
    }

    /**
     * Set the HNSW search parameters for the current transaction (call inside
     * one). SET takes no bind parameters, so the values are interpolated; the
     * casts keep a stray config string from becoming SQL.
     *
     * The tenant and source filters are checked after the index returns a
     * candidate, so iterative scan keeps the walk going until the channel's
     * LIMIT is filled instead of stopping at the first `ef_search` rows.
     *
     * @param  array<string, mixed>  $config  the `chatvia.vector` config
     */
    public function configureAccuracy(string $accuracyLevel, array $config): void
    {
        $efSearch = (int) ($config['search']['ef_search'][$accuracyLevel]
            ?? $config['search']['ef_search']['default']
            ?? 100);

        DB::statement('SET LOCAL hnsw.ef_search = '.max(1, min(1000, $efSearch)));
        DB::statement('SET LOCAL hnsw.iterative_scan = relaxed_order');
    }

    /**
     * The exact path: one materialized pass scores every chunk the agent
     * reaches on all channels, then each channel keeps its best. The pass
     * starts at the agent's sources and reads their chunks through
     * `chunks_page_idx` (the LATERAL subquery, fenced by `OFFSET 0` so the
     * planner keeps that order and cannot swap in the workspace_id btree),
     * never the rest of a large workspace. The tenant filter applies to the
     * rows it returns, before any score is computed.
     *
     * @return list<RetrievedChunkData>
     */
    private function exact(Agent $agent, string $query, string $queryVector, int $limit): array
    {
        $cast = $this->storage->columnType();
        $hybrid = $this->hybrid();
        $candidateLimit = $limit * self::CANDIDATES_PER_RESULT;

        // The query text is parsed once, not once per chunk. A score of 0
        // means no shared term: not a keyword hit (LexicalSearch).
        $keywordInputs = $hybrid ? <<<SQL
,
           {$this->lexical->queryExpression('chunks_search_text_lexical')} AS chunk_terms
SQL : '';
        $keywordScores = $hybrid ? <<<SQL
,
           NULLIF({$this->lexical->score('c.search_text', 'q.chunk_terms')}, 0) AS bm25_dist
SQL : '';

        $channels = [
            ['name' => 'vector_ranked', 'type' => 'vector', 'column' => 'vector_dist'],
            ['name' => 'question_ranked', 'type' => 'question', 'column' => 'question_dist'],
        ];

        if ($hybrid) {
            $channels[] = ['name' => 'bm25_ranked', 'type' => 'bm25', 'column' => 'bm25_dist'];
        }

        $definitions = implode(",\n", array_map(static fn (array $channel): string => <<<SQL
{$channel['name']} AS (
    SELECT t.id, ROW_NUMBER() OVER (ORDER BY t.dist, t.id) AS rank, '{$channel['type']}' AS search_type
    FROM (
        SELECT id, {$channel['column']} AS dist
        FROM scope
        WHERE {$channel['column']} IS NOT NULL
        ORDER BY {$channel['column']}, id
        LIMIT ?
    ) t
)
SQL, $channels));

        $sql = <<<SQL
WITH search AS MATERIALIZED (
    SELECT ?::{$cast} AS vector{$keywordInputs}
),
scope AS MATERIALIZED (
    SELECT c.id,
           c.embedding <-> q.vector AS vector_dist,
           c.questions_embedding <-> q.vector AS question_dist{$keywordScores}
    FROM search q
    CROSS JOIN agent_sources s
    CROSS JOIN LATERAL (
        SELECT c.id, c.workspace_id, c.embedding, c.questions_embedding, c.search_text
        FROM chunks c
        WHERE c.chunkable_type = s.sourceable_type
          AND c.chunkable_id = s.sourceable_id
        OFFSET 0
    ) c
    WHERE s.agent_id = ?
      AND c.workspace_id = ?
),
{$definitions},
{$this->fusion(array_column($channels, 'name'), 'q.vector', "\nCROSS JOIN search q")}
SQL;

        $bindings = [
            $queryVector,
            ...($hybrid ? [$this->lexical->queryText($query)] : []),
            $agent->id,
            (int) $agent->workspace_id,
            ...array_fill(0, count($channels), $candidateLimit),
            $limit,
        ];

        return array_map(RetrievedChunkData::fromRow(...), DB::select($sql, $bindings));
    }

    /**
     * The indexed path: each channel is served by its own index.
     *
     * @return list<RetrievedChunkData>
     */
    private function indexed(Agent $agent, string $query, string $queryVector, int $limit): array
    {
        $channels = $this->indexedChannels($agent, $query, $queryVector, $limit * self::CANDIDATES_PER_RESULT);

        // Each channel carries its bindings in its textual `?` order; the
        // final SELECT measures each result's similarity to the query, and
        // the final LIMIT comes last.
        $bindings = [
            ...array_merge(...array_column($channels, 'bindings')),
            $queryVector,
            $queryVector,
            $limit,
        ];

        $cast = $this->storage->columnType();
        $sql = 'WITH '.implode(",\n", array_column($channels, 'sql')).",\n".$this->fusion(array_column($channels, 'name'), "?::{$cast}");

        return array_map(RetrievedChunkData::fromRow(...), DB::select($sql, $bindings));
    }

    /**
     * The ranked channels of the indexed path: always the two vector channels,
     * plus the keyword channel when hybrid search is on.
     *
     * @return list<array{name: string, sql: string, bindings: list<int|string>}>
     */
    private function indexedChannels(Agent $agent, string $query, string $queryVector, int $candidateLimit): array
    {
        $channels = [
            $this->denseChannel('vector_ranked', 'vector', 'c.embedding', null, $agent, $queryVector, $candidateLimit),
            $this->denseChannel('question_ranked', 'question', 'c.questions_embedding', 'c.questions_embedding IS NOT NULL', $agent, $queryVector, $candidateLimit),
        ];

        if (! $this->hybrid()) {
            return $channels;
        }

        $terms = $this->lexical->queryText($query);
        $match = $this->lexical->matchCondition('c.search_text');

        return [
            ...$channels,
            [
                'name' => 'bm25_ranked',
                'sql' => $this->indexedChannel('bm25_ranked', 'bm25', $this->lexical->distance('c.search_text', 'chunks_search_text_lexical'), $match),
                'bindings' => [$terms, (int) $agent->workspace_id, ...($match !== null ? [$terms] : []), $agent->id, $candidateLimit],
            ],
        ];
    }

    /**
     * A vector channel, ranked by the full stored vector. Over a binary index
     * the index ranks by Hamming distance, which only approximates the real
     * order, so the channel takes `binary_rescore` times as many candidates
     * from it and keeps the closest of those by the stored vector.
     *
     * @return array{name: string, sql: string, bindings: list<int|string>}
     */
    private function denseChannel(string $name, string $searchType, string $column, ?string $requiredColumn, Agent $agent, string $queryVector, int $candidateLimit): array
    {
        $cast = $this->storage->columnType();
        $distance = "{$column} <-> ?::{$cast}";
        $filters = [(int) $agent->workspace_id, $agent->id];

        if ($this->index->kind() === VectorIndex::Full) {
            return [
                'name' => $name,
                'sql' => $this->indexedChannel($name, $searchType, $distance, $requiredColumn),
                'bindings' => [$queryVector, ...$filters, $candidateLimit],
            ];
        }

        $rescore = max(1, (int) config('chatvia.vector.search.binary_rescore', 4));

        return [
            'name' => $name,
            'sql' => $this->indexedChannel($name, $searchType, $distance, $requiredColumn, $this->index->orderBy($column, "?::{$cast}")),
            'bindings' => [$queryVector, ...$filters, $queryVector, $candidateLimit * $rescore, $candidateLimit],
        ];
    }

    /**
     * One ranked channel of the indexed path: the agent's closest chunks by
     * `$distance`, numbered by rank. With `$indexOrder` (a binary index) the
     * index walk sorts by that instead, and the closest by `$distance` of what
     * it returns are kept (a second LIMIT).
     *
     * Both filters are written so that the only way to answer the query is the
     * channel's own index, which visits rows closest-first and checks the
     * filters on each (the dense channels keep scanning under
     * `hnsw.iterative_scan` until the LIMIT is filled). Otherwise the planner,
     * misjudging how many rows match, scores every one of a large agent's
     * chunks, once per channel:
     * - `c.workspace_id + 0` is the tenant filter kept off the workspace_id
     *   btree; as a cheap scalar check it runs before the EXISTS;
     * - `OFFSET 0` keeps the EXISTS a per-row check (an index-only probe of
     *   agent_sources) instead of a join that starts from the agent's sources.
     */
    private function indexedChannel(string $name, string $searchType, string $distance, ?string $requiredColumn = null, ?string $indexOrder = null): string
    {
        $required = $requiredColumn !== null ? "\n          AND {$requiredColumn}" : '';
        $order = $indexOrder ?? 'dist';
        $rescore = $indexOrder !== null ? "\n    ORDER BY t.dist\n    LIMIT ?" : '';

        return <<<SQL
{$name} AS (
    SELECT t.id, ROW_NUMBER() OVER (ORDER BY t.dist) AS rank, '{$searchType}' AS search_type
    FROM (
        SELECT c.id, {$distance} AS dist
        FROM chunks c
        WHERE c.workspace_id + 0 = ?{$required}
          AND EXISTS (
              SELECT 1 FROM agent_sources s
              WHERE s.agent_id = ?
                AND s.sourceable_id = c.chunkable_id
                AND s.sourceable_type = c.chunkable_type
              OFFSET 0
          )
        ORDER BY {$order}
        LIMIT ?
    ) t{$rescore}
)
SQL;
    }

    /**
     * RRF over the channels, then the chunk rows of the best `LIMIT ?`, each
     * with its cosine similarity to the query (the closer of the chunk's own
     * vector and its questions' vector) for the similarity check, and its
     * size in tokens for the context budget.
     *
     * @param  list<string>  $channelNames
     * @param  string  $vector  the SQL expression of the query vector (used twice)
     * @param  string  $join  what makes that expression available
     */
    private function fusion(array $channelNames, string $vector, string $join = ''): string
    {
        $combined = implode("\n    UNION ALL\n", array_map(
            static fn (string $name): string => "    SELECT id, rank, search_type FROM {$name}",
            $channelNames,
        ));

        return <<<SQL
combined_results AS (
{$combined}
),
rrf_scores AS (
    SELECT id,
           STRING_AGG(DISTINCT search_type, '+') AS search_type,
           SUM(1.0 / (60 + rank)) AS rrf_score
    FROM combined_results
    GROUP BY id
)
SELECT c.id, c.external_id, c.chunk, c.chunk_index, c.chunkable_type, c.chunkable_id, c.metadata, c.tokens, c.created_at,
       r.search_type, r.rrf_score AS score,
       GREATEST(1 - (c.embedding <=> {$vector}), COALESCE(1 - (c.questions_embedding <=> {$vector}), 0)) AS similarity
FROM rrf_scores r
JOIN chunks c USING (id){$join}
ORDER BY r.rrf_score DESC, r.id
LIMIT ?
SQL;
    }

    /** Unset (no admin setting applied yet) means on, the default. */
    private function hybrid(): bool
    {
        return (bool) (config('chatvia.vector.search.hybrid') ?? true);
    }
}
