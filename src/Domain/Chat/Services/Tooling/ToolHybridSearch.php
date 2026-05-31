<?php

namespace Domain\Chat\Services\Tooling;

use Domain\Agents\Models\Agent;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Per-turn hybrid retrieval over **both** `mcp_tools` and `functions`.
 * One SQL query, four channels, one RRF scoreboard — so an MCP tool
 * and a webhook function compete on equal footing for the same
 * `MAX_TOOLS` slots:
 *
 *   - `mcp_vector` / `mcp_bm25`  → mcp_tools dense + sparse signals
 *   - `fn_vector`  / `fn_bm25`   → functions  dense + sparse signals
 *
 * RRF (k = 60) sums `1 / (k + rank)` across whichever channels saw
 * each candidate. The result is a unified score where rank-1 in the
 * function pool is comparable to rank-1 in the MCP pool, even though
 * the absolute pool sizes differ wildly.
 *
 * Pool scoping happens inside each channel's CTE:
 *   - MCP rows require `mcp_servers.enabled` + `mcp_tools.enabled` +
 *     an `agent_mcp_server` pivot row for this agent.
 *   - Function rows require `functions.enabled` + an `agent_function`
 *     pivot row for this agent.
 */
class ToolHybridSearch
{
    /** vchordrq + BM25 indexes were built against this dim. */
    private const EMBEDDING_DIMS = 1024;

    /**
     * RRF constant. 60 is the de-facto default (Cormack et al.) — it
     * smooths per-channel rank differences without letting any single
     * top hit dominate the fusion.
     */
    private const RRF_K = 60;

    /**
     * @param  array<int, float>  $queryVector
     * @return array<int, array{entry: ToolEntry, score: float, src: string, kind: string}>
     */
    public function search(Agent $agent, string $userMessage, array $queryVector, int $limit = 30): array
    {
        $vectorString = '['.implode(',', $queryVector).']';
        $candidateLimit = max($limit * 3, $limit);
        $dims = self::EMBEDDING_DIMS;
        $rrfK = self::RRF_K;

        $sql = <<<SQL
WITH mcp_vector AS (
    SELECT 'mcp' AS kind, t.id,
           ROW_NUMBER() OVER (ORDER BY t.embedding <-> ?::vector({$dims})) AS rank
    FROM mcp_tools t
    INNER JOIN mcp_servers s ON s.id = t.mcp_server_id
    INNER JOIN agent_mcp_server ams ON ams.mcp_server_id = s.id AND ams.agent_id = ?
    WHERE s.enabled = true
      AND t.enabled = true
      AND t.embedding IS NOT NULL
    ORDER BY t.embedding <-> ?::vector({$dims})
    LIMIT ?
),
mcp_bm25 AS (
    SELECT 'mcp' AS kind, t.id,
           ROW_NUMBER() OVER (
               ORDER BY t.embedding_bm25 <&> bm25_catalog.to_bm25query(
                   'mcp_tools_embedding_bm25',
                   tokenizer_catalog.tokenize(?::text, 'llmlingua2')
               )
           ) AS rank
    FROM mcp_tools t
    INNER JOIN mcp_servers s ON s.id = t.mcp_server_id
    INNER JOIN agent_mcp_server ams ON ams.mcp_server_id = s.id AND ams.agent_id = ?
    WHERE s.enabled = true
      AND t.enabled = true
      AND t.embedding_bm25 IS NOT NULL
    ORDER BY rank
    LIMIT ?
),
fn_vector AS (
    SELECT 'fn' AS kind, f.id,
           ROW_NUMBER() OVER (ORDER BY f.embedding <-> ?::vector({$dims})) AS rank
    FROM functions f
    INNER JOIN agent_function af ON af.function_id = f.id AND af.agent_id = ?
    WHERE f.enabled = true
      AND f.embedding IS NOT NULL
    ORDER BY f.embedding <-> ?::vector({$dims})
    LIMIT ?
),
fn_bm25 AS (
    SELECT 'fn' AS kind, f.id,
           ROW_NUMBER() OVER (
               ORDER BY f.embedding_bm25 <&> bm25_catalog.to_bm25query(
                   'functions_embedding_bm25',
                   tokenizer_catalog.tokenize(?::text, 'llmlingua2')
               )
           ) AS rank
    FROM functions f
    INNER JOIN agent_function af ON af.function_id = f.id AND af.agent_id = ?
    WHERE f.enabled = true
      AND f.embedding_bm25 IS NOT NULL
    ORDER BY rank
    LIMIT ?
),
combined AS (
    SELECT kind, id, rank, 'vector' AS src FROM mcp_vector
    UNION ALL
    SELECT kind, id, rank, 'bm25'   AS src FROM mcp_bm25
    UNION ALL
    SELECT kind, id, rank, 'vector' AS src FROM fn_vector
    UNION ALL
    SELECT kind, id, rank, 'bm25'   AS src FROM fn_bm25
),
rrf AS (
    SELECT kind, id,
           STRING_AGG(DISTINCT src, '+') AS src,
           SUM(1.0 / ({$rrfK} + rank))   AS score
    FROM combined
    GROUP BY kind, id
)
SELECT r.kind, r.id, r.score, r.src,
       t.name              AS mcp_name,
       t.description       AS mcp_description,
       t.input_schema      AS mcp_schema,
       s.slug              AS mcp_server_slug,
       f.name              AS fn_name,
       f.description       AS fn_description,
       f.parameters_schema AS fn_schema
FROM rrf r
LEFT JOIN mcp_tools   t ON r.kind = 'mcp' AND t.id = r.id
LEFT JOIN mcp_servers s ON r.kind = 'mcp' AND s.id = t.mcp_server_id
LEFT JOIN functions   f ON r.kind = 'fn'  AND f.id = r.id
ORDER BY r.score DESC
LIMIT ?
SQL;

        $startedAt = microtime(true);

        // No per-query vchordrq GUCs. prefilter is a database-wide default
        // (ALTER DATABASE ... SET vchordrq.prefilter = on, in the vchordrq
        // install migration); probes stays at its empty default because the flat
        // mcp_tools/functions indexes (no `lists`) require 0 probes. So this runs
        // as a plain query with no wrapping transaction.
        $rows = DB::select($sql, [
            $vectorString, $agent->id, $vectorString, $candidateLimit,  // mcp_vector
            $userMessage, $agent->id, $candidateLimit,                  // mcp_bm25
            $vectorString, $agent->id, $vectorString, $candidateLimit,  // fn_vector
            $userMessage, $agent->id, $candidateLimit,                  // fn_bm25
            $limit,                                                      // final LIMIT
        ]);
        $elapsedMs = (microtime(true) - $startedAt) * 1000;

        $results = array_map(static function (object $row): array {
            if ($row->kind === 'mcp') {
                $schema = is_string($row->mcp_schema) ? json_decode($row->mcp_schema, true) : null;
                $entry = new ToolEntry(
                    kind: 'mcp',
                    sourceId: (int) $row->id,
                    name: 'mcp__'.$row->mcp_server_slug.'__'.$row->mcp_name,
                    description: (string) $row->mcp_description,
                    parameters: is_array($schema) ? $schema : [],
                    embedding: null,
                );
            } else {
                $schema = is_string($row->fn_schema) ? json_decode($row->fn_schema, true) : null;
                $entry = new ToolEntry(
                    kind: 'function',
                    sourceId: (int) $row->id,
                    name: (string) $row->fn_name,
                    description: (string) $row->fn_description,
                    parameters: is_array($schema) ? $schema : [],
                    embedding: null,
                );
            }

            return [
                'entry' => $entry,
                'score' => (float) $row->score,
                'src' => (string) $row->src,
                'kind' => (string) $row->kind,
            ];
        }, $rows);

        Log::debug('ToolHybridSearch: search complete', [
            'agent_id' => $agent->id,
            'query_chars' => strlen($userMessage),
            'returned' => count($results),
            'elapsed_ms' => round($elapsedMs, 2),
            'results' => array_map(static fn (array $r): array => [
                'kind' => $r['kind'],
                'name' => $r['entry']->name,
                'score' => round($r['score'], 5),
                'src' => $r['src'],
            ], $results),
        ]);

        return $results;
    }
}
