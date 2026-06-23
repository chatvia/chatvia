<?php

namespace Domain\Conversation\Services\Tooling\Routing;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Tooling\Registry\ToolEntry;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Per-turn hybrid retrieval across the preset catalog, custom MCP tools, and
 * webhook functions. One SQL query, six channels, one RRF scoreboard — so a
 * preset tool, a custom MCP tool and a function compete on equal footing for the
 * same `MAX_TOOLS` slots:
 *
 *   - `preset_vector` / `preset_bm25`  → shared `connector_tools` catalog
 *   - `custom_vector` / `custom_bm25`  → custom servers' `mcp_tools`
 *   - `fn_vector`     / `fn_bm25`       → `functions`
 *
 * RRF (k = 60) sums `1 / (k + rank)` across whichever channels saw each
 * candidate, yielding a unified score across pools of wildly different sizes.
 *
 * Pool scoping:
 *   - PRESET rows rank over the shared `connector_tools` embedding index,
 *     filtered against `reachable_preset` — the agent's reachable catalog-tool
 *     ids, precomputed once from its handful of servers (attached via
 *     `agent_mcp_server` OR a global builtin) joined to their unblocked
 *     `mcp_server_tool` memberships. Driving from the agent's servers keeps
 *     the per-candidate check a hashed set probe; a correlated EXISTS would
 *     instead walk every workspace's membership rows for each candidate,
 *     scaling with the connector's popularity. The final SELECT resolves one
 *     reachable server slug for the `mcp__{slug}__{tool}` name.
 *   - PER-SERVER rows (custom servers + workspace-URL presets,
 *     `uses_central_catalog = false`) require `mcp_servers.is_enabled` +
 *     `mcp_tools.permission` other than `blocked` + agent reachability.
 *   - Function rows require `functions.is_enabled` + an `agent_function` pivot
 *     row for this agent.
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
WITH reachable_servers AS (
    -- The agent's attached servers (via the agent_mcp_server pivot). A handful
    -- of rows, driven from the agent_mcp_server PK — never from the
    -- membership table, whose size scales with the whole customer base.
    SELECT s.id, s.slug
    FROM mcp_servers s
    INNER JOIN agent_mcp_server ams ON ams.mcp_server_id = s.id AND ams.agent_id = ?
    WHERE s.is_enabled = true
),
reachable_preset AS (
    -- Catalog tools those servers advertise with an unblocked membership.
    SELECT DISTINCT mst.connector_tool_id
    FROM reachable_servers rs
    INNER JOIN mcp_server_tool mst ON mst.mcp_server_id = rs.id
    WHERE mst.permission != 'blocked'
),
preset_vector AS (
    SELECT 'mcp_preset' AS kind, ct.id,
           ROW_NUMBER() OVER (ORDER BY ct.embedding <-> ?::vector({$dims})) AS rank
    FROM connector_tools ct
    WHERE ct.embedding IS NOT NULL
      AND ct.id IN (SELECT connector_tool_id FROM reachable_preset)
    ORDER BY ct.embedding <-> ?::vector({$dims})
    LIMIT ?
),
preset_bm25 AS (
    SELECT 'mcp_preset' AS kind, ct.id,
           ROW_NUMBER() OVER (
               ORDER BY ct.embedding_bm25 <&> bm25_catalog.to_bm25query(
                   'connector_tools_embedding_bm25',
                   tokenizer_catalog.tokenize(?::text, 'llmlingua2')
               )
           ) AS rank
    FROM connector_tools ct
    WHERE ct.embedding_bm25 IS NOT NULL
      AND ct.id IN (SELECT connector_tool_id FROM reachable_preset)
    ORDER BY rank
    LIMIT ?
),
custom_vector AS (
    SELECT 'mcp_custom' AS kind, t.id,
           ROW_NUMBER() OVER (ORDER BY t.embedding <-> ?::vector({$dims})) AS rank
    FROM mcp_tools t
    INNER JOIN mcp_servers s ON s.id = t.mcp_server_id
    LEFT JOIN agent_mcp_server ams ON ams.mcp_server_id = s.id AND ams.agent_id = ?
    WHERE s.is_enabled = true
      AND s.uses_central_catalog = false
      AND t.permission != 'blocked'
      AND t.embedding IS NOT NULL
      AND ams.agent_id IS NOT NULL
    ORDER BY t.embedding <-> ?::vector({$dims})
    LIMIT ?
),
custom_bm25 AS (
    SELECT 'mcp_custom' AS kind, t.id,
           ROW_NUMBER() OVER (
               ORDER BY t.embedding_bm25 <&> bm25_catalog.to_bm25query(
                   'mcp_tools_embedding_bm25',
                   tokenizer_catalog.tokenize(?::text, 'llmlingua2')
               )
           ) AS rank
    FROM mcp_tools t
    INNER JOIN mcp_servers s ON s.id = t.mcp_server_id
    LEFT JOIN agent_mcp_server ams ON ams.mcp_server_id = s.id AND ams.agent_id = ?
    WHERE s.is_enabled = true
      AND s.uses_central_catalog = false
      AND t.permission != 'blocked'
      AND t.embedding_bm25 IS NOT NULL
      AND ams.agent_id IS NOT NULL
    ORDER BY rank
    LIMIT ?
),
fn_vector AS (
    SELECT 'fn' AS kind, f.id,
           ROW_NUMBER() OVER (ORDER BY f.embedding <-> ?::vector({$dims})) AS rank
    FROM functions f
    INNER JOIN agent_function af ON af.function_id = f.id AND af.agent_id = ?
    WHERE f.is_enabled = true
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
    WHERE f.is_enabled = true
      AND f.embedding_bm25 IS NOT NULL
    ORDER BY rank
    LIMIT ?
),
combined AS (
    SELECT kind, id, rank, 'vector' AS src FROM preset_vector
    UNION ALL
    SELECT kind, id, rank, 'bm25'   AS src FROM preset_bm25
    UNION ALL
    SELECT kind, id, rank, 'vector' AS src FROM custom_vector
    UNION ALL
    SELECT kind, id, rank, 'bm25'   AS src FROM custom_bm25
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
       pt.name              AS preset_name,
       pt.description        AS preset_description,
       pt.input_schema      AS preset_schema,
       pt.scope             AS preset_scope,
       ps.slug              AS preset_server_slug,
       t.name               AS custom_name,
       t.description         AS custom_description,
       t.input_schema       AS custom_schema,
       t.scope              AS custom_scope,
       cs.slug              AS custom_server_slug,
       f.internal_name      AS fn_name,
       f.description         AS fn_description,
       f.parameters_schema  AS fn_schema,
       f.webhook_method     AS fn_method
FROM rrf r
LEFT JOIN connector_tools pt ON r.kind = 'mcp_preset' AND pt.id = r.id
LEFT JOIN LATERAL (
    -- Pick one agent-reachable server slug for this catalog tool, so the
    -- compiled name uses a slug the agent can actually dispatch to.
    SELECT rs.slug
    FROM reachable_servers rs
    INNER JOIN mcp_server_tool mst
        ON mst.mcp_server_id = rs.id AND mst.connector_tool_id = r.id
    WHERE mst.permission != 'blocked'
    ORDER BY rs.slug
    LIMIT 1
) ps ON r.kind = 'mcp_preset'
LEFT JOIN mcp_tools   t  ON r.kind = 'mcp_custom' AND t.id = r.id
LEFT JOIN mcp_servers cs ON r.kind = 'mcp_custom' AND cs.id = t.mcp_server_id
LEFT JOIN functions   f  ON r.kind = 'fn'         AND f.id = r.id
ORDER BY r.score DESC
LIMIT ?
SQL;

        $startedAt = microtime(true);

        // No per-query vchordrq GUCs. prefilter is a database-wide default
        // (ALTER DATABASE ... SET vchordrq.prefilter = on, in the vchordrq
        // install migration) — it pushes each channel's reachability filter
        // (the hashed IN-probe against the materialized reachable_preset set,
        // or the custom channels' WHERE) into the index scan, so the
        // per-channel LIMIT returns the top-N *reachable* rows. probes stays
        // at its empty default because the flat connector_tools/mcp_tools/
        // functions indexes (no `lists`) require 0 probes. So this runs as a
        // plain query with no wrapping transaction.
        $rows = DB::select($sql, [
            $agent->id,                                                 // reachable_servers
            $vectorString, $vectorString, $candidateLimit,              // preset_vector
            $userMessage, $candidateLimit,                              // preset_bm25
            $vectorString, $agent->id, $vectorString, $candidateLimit,  // custom_vector
            $userMessage, $agent->id, $candidateLimit,                  // custom_bm25
            $vectorString, $agent->id, $vectorString, $candidateLimit,  // fn_vector
            $userMessage, $agent->id, $candidateLimit,                  // fn_bm25
            $limit,                                                      // final LIMIT
        ]);
        $elapsedMs = (microtime(true) - $startedAt) * 1000;

        $results = array_map(static function (object $row): array {
            if ($row->kind === 'mcp_preset') {
                $schema = is_string($row->preset_schema) ? json_decode($row->preset_schema, true) : $row->preset_schema;
                $entry = new ToolEntry(
                    kind: 'mcp',
                    sourceId: 'connector_tool:'.$row->id,
                    name: 'mcp__'.$row->preset_server_slug.'__'.$row->preset_name,
                    description: (string) $row->preset_description,
                    parameters: is_array($schema) ? $schema : [],
                    embedding: null,
                    mutates: $row->preset_scope !== 'read',
                );
            } elseif ($row->kind === 'mcp_custom') {
                $schema = is_string($row->custom_schema) ? json_decode($row->custom_schema, true) : $row->custom_schema;
                $entry = new ToolEntry(
                    kind: 'mcp',
                    sourceId: 'mcp_tool:'.$row->id,
                    name: 'mcp__'.$row->custom_server_slug.'__'.$row->custom_name,
                    description: (string) $row->custom_description,
                    parameters: is_array($schema) ? $schema : [],
                    embedding: null,
                    mutates: $row->custom_scope !== 'read',
                );
            } else {
                $schema = is_string($row->fn_schema) ? json_decode($row->fn_schema, true) : $row->fn_schema;
                $entry = new ToolEntry(
                    kind: 'function',
                    sourceId: 'function:'.$row->id,
                    name: (string) $row->fn_name,
                    description: (string) $row->fn_description,
                    parameters: is_array($schema) ? $schema : [],
                    embedding: null,
                    mutates: strtoupper((string) $row->fn_method) !== 'GET',
                );
            }

            return [
                'entry' => $entry,
                'score' => (float) $row->score,
                'src' => (string) $row->src,
                'kind' => (string) $row->kind,
            ];
        }, $rows);

        // De-dupe by compiled tool name — a slug can surface as both an
        // attached row and a global built-in; rows are score-ordered, so the
        // first occurrence is the strongest.
        $seen = [];
        $results = array_values(array_filter($results, static function (array $r) use (&$seen): bool {
            $name = $r['entry']->name;
            if (isset($seen[$name])) {
                return false;
            }
            $seen[$name] = true;

            return true;
        }));

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
