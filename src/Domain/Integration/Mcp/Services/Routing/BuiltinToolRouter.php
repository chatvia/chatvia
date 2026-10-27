<?php

namespace Domain\Integration\Mcp\Services\Routing;

use Domain\Integration\Mcp\Enums\McpServerCategory;
use Domain\Integration\Tool\Search\ToolHybridSearch;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProvider;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;
use Domain\Knowledge\Retrieval\Services\LexicalSearch;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Semantic tool routing for the agent-less "Ask your data" chat. The
 * GLOBAL (workspace_id IS NULL) counterpart of {@see ToolHybridSearch}:
 * the same RRF over `connector_tools` dense (`<->`) + keyword (LexicalSearch)
 * signals, but scoped by `mcp_servers.category` instead of an agent pivot, and
 * without the `functions` channels (the analytics chat has no webhook tools).
 *
 * Returns OpenAI tool definitions (raw tool names — the analytics chat
 * dispatches by name → URL, not by an `mcp__slug__` prefix) plus a
 * name → endpoint map. Degrades gracefully: missing query embedding → keyword
 * only; a query error → unranked list; no rows → empty (the runner then falls
 * back to its reflection-built toolset, so the chat works before the first sync).
 */
class BuiltinToolRouter
{
    /** RRF constant — the Cormack et al. default, as in ToolHybridSearch. */
    private const int RRF_K = 60;

    public function __construct(
        private readonly EmbeddingsProvider $embeddings,
        private readonly EmbeddingStorage $storage,
        private readonly LexicalSearch $lexical,
    ) {}

    /**
     * @param  array<int, McpServerCategory>  $categories
     * @return array{definitions: array<int, array<string, mixed>>, routes: array<string, string>}
     */
    public function route(string $question, array $categories, int $limit = 12): array
    {
        $catValues = array_values(array_map(static fn (McpServerCategory $c): string => $c->value, $categories));
        if ($catValues === []) {
            return ['definitions' => [], 'routes' => []];
        }

        try {
            $rows = $this->search($question, $catValues, $this->embedQuestion($question), $limit);
        } catch (Throwable $e) {
            Log::warning('BuiltinToolRouter ranked search failed; trying unranked', ['error' => $e->getMessage()]);

            try {
                $rows = $this->unranked($catValues, $limit);
            } catch (Throwable $e2) {
                // Registry not ready (e.g. pre-migration / pre-sync). Return
                // empty so the runner falls back to its reflection toolset.
                Log::warning('BuiltinToolRouter unavailable; using runner fallback', ['error' => $e2->getMessage()]);

                return ['definitions' => [], 'routes' => []];
            }
        }

        $definitions = [];
        $routes = [];

        foreach ($rows as $row) {
            $schema = is_scalar($row->input_schema) ? json_decode((string) $row->input_schema, true) : $row->input_schema;

            $definitions[] = [
                'type' => 'function',
                'function' => [
                    'name' => $row->name,
                    'description' => (string) $row->description,
                    'parameters' => is_array($schema) ? $schema : ['type' => 'object', 'properties' => (object) []],
                ],
            ];
            $routes[$row->name] = (string) $row->url;
        }

        return ['definitions' => $definitions, 'routes' => $routes];
    }

    /**
     * @return array<int, float>|null
     */
    private function embedQuestion(string $question): ?array
    {
        try {
            $vector = $this->embeddings->embedQuery($question);

            return is_array($vector) ? $vector : null;
        } catch (Throwable $e) {
            Log::warning('BuiltinToolRouter query embedding failed; keyword only', ['error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * @param  array<int, string>  $categories
     * @param  array<int, float>|null  $vector
     * @return array<int, object>
     */
    private function search(string $question, array $categories, ?array $vector, int $limit): array
    {
        $placeholders = Arr::join(array_fill(0, count($categories), '?'), ',');

        if ($vector === null) {
            $sql = <<<SQL
SELECT ct.name, ct.description, ct.input_schema, s.url
FROM connector_tools ct
INNER JOIN connector_tool_mcp_server mst ON mst.connector_tool_id = ct.id
INNER JOIN mcp_servers s ON s.id = mst.mcp_server_id
WHERE s.workspace_id IS NULL AND s.is_enabled = true AND s.category IN ($placeholders)
  AND mst.permission != 'blocked' AND ct.search_text IS NOT NULL
ORDER BY {$this->lexical->distance('ct.search_text', 'connector_tools_search_text_lexical')}
LIMIT ?
SQL;

            return DB::select($sql, [...$categories, $this->lexical->queryText($question), $limit]);
        }

        $vectorString = '['.Arr::join($vector, ',').']';
        $candidate = max($limit * 3, $limit);
        // The query vector takes the stored embeddings' type and size.
        $cast = $this->storage->columnType();
        $rrfK = self::RRF_K;

        $sql = <<<SQL
WITH scoped AS (
    SELECT ct.id, ct.name, ct.description, ct.input_schema, s.url, ct.embedding, ct.search_text
    FROM connector_tools ct
    INNER JOIN connector_tool_mcp_server mst ON mst.connector_tool_id = ct.id
    INNER JOIN mcp_servers s ON s.id = mst.mcp_server_id
    WHERE s.workspace_id IS NULL AND s.is_enabled = true AND s.category IN ($placeholders)
      AND mst.permission != 'blocked'
),
vec AS (
    SELECT id, ROW_NUMBER() OVER (ORDER BY embedding <-> ?::{$cast}) AS rank
    FROM scoped WHERE embedding IS NOT NULL
    ORDER BY embedding <-> ?::{$cast}
    LIMIT ?
),
bm25 AS (
    SELECT scoped.id, ROW_NUMBER() OVER (
        ORDER BY {$this->lexical->score('scoped.search_text', 'q.terms')}
    ) AS rank
    FROM (SELECT {$this->lexical->queryExpression('connector_tools_search_text_lexical')} AS terms) q
    CROSS JOIN scoped
    WHERE scoped.search_text IS NOT NULL
    ORDER BY rank
    LIMIT ?
),
combined AS (
    SELECT id, rank FROM vec
    UNION ALL
    SELECT id, rank FROM bm25
),
rrf AS (
    SELECT id, SUM(1.0 / ({$rrfK} + rank)) AS score
    FROM combined GROUP BY id
)
SELECT sc.name, sc.description, sc.input_schema, sc.url
FROM rrf r
INNER JOIN scoped sc ON sc.id = r.id
ORDER BY r.score DESC
LIMIT ?
SQL;

        return DB::select($sql, [
            ...$categories,                                 // scoped
            $vectorString, $vectorString, $candidate,       // vec
            $this->lexical->queryText($question), $candidate, // bm25
            $limit,                                         // final
        ]);
    }

    /**
     * Every tool for the given categories, unranked — used when the ranked
     * query errors (e.g. the vector extension is unavailable in a test env).
     *
     * @param  array<int, string>  $categories
     * @return array<int, object>
     */
    private function unranked(array $categories, int $limit): array
    {
        $placeholders = Arr::join(array_fill(0, count($categories), '?'), ',');

        return DB::select(<<<SQL
SELECT ct.name, ct.description, ct.input_schema, s.url
FROM connector_tools ct
INNER JOIN connector_tool_mcp_server mst ON mst.connector_tool_id = ct.id
INNER JOIN mcp_servers s ON s.id = mst.mcp_server_id
WHERE s.workspace_id IS NULL AND s.is_enabled = true AND s.category IN ($placeholders)
  AND mst.permission != 'blocked'
ORDER BY ct.name
LIMIT ?
SQL, [...$categories, $limit]);
    }
}
