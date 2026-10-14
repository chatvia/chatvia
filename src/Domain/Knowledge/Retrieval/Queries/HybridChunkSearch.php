<?php

namespace Domain\Knowledge\Retrieval\Queries;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;
use Domain\Knowledge\Retrieval\Data\RetrievedChunkData;
use Domain\Knowledge\Retrieval\Enums\ChunkSearchScope;
use Domain\Knowledge\Retrieval\Services\VectorIndexLayout;
use Domain\Knowledge\Retrieval\Services\VectorIndexSizing;
use Illuminate\Support\Facades\DB;

/**
 * The SQL of the knowledge-base search: four ranked channels over an agent's
 * chunks — dense vector, dense vector on the generated questions, BM25, and
 * BM25 on the questions — fused with Reciprocal Rank Fusion (k = 60). With
 * `chatvia.vector.search.hybrid` off, only the two vector channels run. A
 * BM25 channel only ranks chunks that share a term with the query.
 *
 * The channels reach the agent's chunks in one of two ways
 * ({@see ChunkSearchScope}), chosen from a bounded count of those chunks:
 *
 * - Exact (the agent reaches few chunks): one MATERIALIZED pass over exactly
 *   the agent's chunks scores all four channels at once. Exact distances mean
 *   full recall, and no other workspace's rows are ever read, so the cost
 *   follows the agent's size, not the platform's.
 * - Indexed (many chunks): each channel is a self-contained subquery that the
 *   vchordrq / BM25 index serves in `prefilter` mode, with the tenant filter
 *   (`c.workspace_id`) and the agent's sources (`EXISTS`) checked during the
 *   index scan. The probes follow the index's live partitions.
 *
 * Retrieval quality is measured by the knowledge retrieval eval
 * (tests/Eval/KnowledgeRetrievalEvalTest.php): any change to this SQL changes
 * search results and must be measured first.
 */
final readonly class HybridChunkSearch
{
    /** Each channel returns this many times the final limit before fusion. */
    private const int CANDIDATES_PER_RESULT = 3;

    /** The vchordrq indexes the two dense channels of the indexed path use. */
    private const array VECTOR_INDEXES = [
        'chunks_embedding_vchordrq_optimized',
        'chunks_questions_embedding_vchordrq',
    ];

    public function __construct(
        private EmbeddingStorage $storage,
        private VectorIndexLayout $layout,
        private VectorIndexSizing $sizing,
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
     * Set the vchordrq search parameters for the current transaction (call
     * inside one). SET takes no bind parameters, so the values are
     * interpolated; the casts keep a stray config string from becoming SQL.
     *
     * Probes must match the shape of every vector index the query scans: none
     * for a flat index, one value per level for a partitioned one. The GUC is
     * set only when both indexes are partitioned on one level; otherwise each
     * index uses the default probes stored on it by `vector:reindex`.
     *
     * @param  array<string, mixed>  $config  the `chatvia.vector` config
     */
    public function configureAccuracy(string $accuracyLevel, array $config): void
    {
        $epsilon = (float) ($config['search']['epsilon'][$accuracyLevel]
            ?? $config['search']['epsilon']['default']
            ?? 1.9);

        // prefilter is a database-wide default (vchordrq install migration).
        DB::statement('SET LOCAL vchordrq.epsilon = '.max(0.0, min(4.0, $epsilon)));

        $percent = (float) ($config['search']['probes_percent'][$accuracyLevel]
            ?? $config['search']['probes_percent']['default']
            ?? 3);

        if (($probes = $this->probes($percent)) !== null) {
            DB::statement("SET LOCAL vchordrq.probes = '{$probes}'");
        }
    }

    /**
     * One probes value valid for every vector index, or null to leave each
     * index on its own stored default (a flat index, mixed shapes, or an index
     * missing for a moment while a rebuild swaps it).
     */
    private function probes(float $percent): ?string
    {
        $percent = max(0.01, min(100.0, $percent));
        $probes = 0;

        foreach (self::VECTOR_INDEXES as $index) {
            $lists = $this->layout->lists($index);

            if ($lists === null || count($lists) !== 1) {
                return null;
            }

            $probes = max($probes, (int) $this->sizing->probes($lists, $percent));
        }

        return (string) $probes;
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

        // The query is tokenized and turned into BM25 queries once, not once
        // per chunk. A score of 0 means no shared term: not a BM25 hit.
        $keywordInputs = $hybrid ? <<<'SQL'
,
           bm25_catalog.to_bm25query('chunks_embedding_bm25', tokenizer_catalog.tokenize(?, 'llmlingua2')) AS chunk_terms,
           bm25_catalog.to_bm25query('chunks_questions_embedding_bm25', tokenizer_catalog.tokenize(?, 'llmlingua2')) AS question_terms
SQL : '';
        $keywordScores = $hybrid ? <<<'SQL'
,
           NULLIF(c.embedding_bm25 <&> q.chunk_terms, 0) AS bm25_dist,
           NULLIF(c.questions_embedding_bm25 <&> q.question_terms, 0) AS question_bm25_dist
SQL : '';

        $channels = [
            ['name' => 'vector_ranked', 'type' => 'vector', 'column' => 'vector_dist'],
            ['name' => 'question_ranked', 'type' => 'question', 'column' => 'question_dist'],
        ];

        if ($hybrid) {
            $channels[] = ['name' => 'bm25_ranked', 'type' => 'bm25', 'column' => 'bm25_dist'];
            $channels[] = ['name' => 'question_bm25_ranked', 'type' => 'question_bm25', 'column' => 'question_bm25_dist'];
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
        SELECT c.id, c.workspace_id, c.embedding, c.questions_embedding, c.embedding_bm25, c.questions_embedding_bm25
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
            ...($hybrid ? [$query, $query] : []),
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
        $candidateLimit = $limit * self::CANDIDATES_PER_RESULT;
        $channels = $this->indexedChannels();

        // Bindings follow each channel's textual `?` order: the ranking
        // expression (query vector or text), the tenant filter, the agent id
        // in EXISTS and the channel limit; the final LIMIT comes last.
        $bindings = [];

        foreach ($channels as $channel) {
            $bindings[] = $channel['keyword'] ? $query : $queryVector;
            $bindings[] = (int) $agent->workspace_id;
            $bindings[] = $agent->id;
            $bindings[] = $candidateLimit;
        }

        // The final SELECT measures each result's similarity to the query.
        $bindings[] = $queryVector;
        $bindings[] = $queryVector;
        $bindings[] = $limit;

        $cast = $this->storage->columnType();
        $sql = 'WITH '.implode(",\n", array_column($channels, 'sql')).",\n".$this->fusion(array_column($channels, 'name'), "?::{$cast}");

        return array_map(RetrievedChunkData::fromRow(...), DB::select($sql, $bindings));
    }

    /**
     * The ranked channels of the indexed path: always the two vector channels,
     * plus the two BM25 (keyword) channels when hybrid search is on.
     *
     * @return list<array{name: string, sql: string, keyword: bool}>
     */
    private function indexedChannels(): array
    {
        $cast = $this->storage->columnType();

        $channels = [
            ['name' => 'vector_ranked', 'keyword' => false,
                'sql' => $this->indexedChannel('vector_ranked', 'vector', "c.embedding <-> ?::{$cast}")],
            ['name' => 'question_ranked', 'keyword' => false,
                'sql' => $this->indexedChannel('question_ranked', 'question', "c.questions_embedding <-> ?::{$cast}", 'c.questions_embedding IS NOT NULL')],
        ];

        if (! $this->hybrid()) {
            return $channels;
        }

        return [
            ...$channels,
            ['name' => 'bm25_ranked', 'keyword' => true,
                'sql' => $this->indexedChannel('bm25_ranked', 'bm25', $this->bm25Distance('c.embedding_bm25', 'chunks_embedding_bm25'))],
            ['name' => 'question_bm25_ranked', 'keyword' => true,
                'sql' => $this->indexedChannel('question_bm25_ranked', 'question_bm25', $this->bm25Distance('c.questions_embedding_bm25', 'chunks_questions_embedding_bm25'), 'c.questions_embedding_bm25 IS NOT NULL')],
        ];
    }

    /**
     * One ranked channel of the indexed path: the agent's closest chunks by
     * `$distance`, numbered by rank.
     *
     * Both filters are written so that the only way to answer the query is the
     * channel's own index, which visits rows closest-first and checks the
     * filters on each (prefilter). Otherwise the planner, misjudging how many
     * rows match, scores every one of a large agent's chunks, once per channel:
     * - `c.workspace_id + 0` is the tenant filter kept off the workspace_id
     *   btree; as a cheap scalar check it runs before the EXISTS;
     * - `OFFSET 0` keeps the EXISTS a per-row check (an index-only probe of
     *   agent_sources) instead of a join that starts from the agent's sources.
     */
    private function indexedChannel(string $name, string $searchType, string $distance, ?string $requiredColumn = null): string
    {
        $required = $requiredColumn !== null ? "\n          AND {$requiredColumn}" : '';

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
        ORDER BY dist
        LIMIT ?
    ) t
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
SELECT c.id, c.external_id, c.chunk, c.chunk_index, c.chunkable_type, c.chunkable_id, c.metadata, c.tokens,
       r.search_type, r.rrf_score AS score,
       GREATEST(1 - (c.embedding <=> {$vector}), COALESCE(1 - (c.questions_embedding <=> {$vector}), 0)) AS similarity
FROM rrf_scores r
JOIN chunks c USING (id){$join}
ORDER BY r.rrf_score DESC, r.id
LIMIT ?
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

    /** Unset (no admin setting applied yet) means on, the default. */
    private function hybrid(): bool
    {
        return (bool) (config('chatvia.vector.search.hybrid') ?? true);
    }
}
