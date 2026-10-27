<?php

namespace Domain\Form\Services;

use Domain\Agent\Models\Agent;
use Domain\Form\Models\FormDefinition;
use Domain\Integration\Tool\Search\ToolHybridSearch;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProvider;
use Domain\Knowledge\Embedding\Services\EmbeddingStorage;
use Domain\Knowledge\Retrieval\Services\LexicalSearch;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Intent-driven retrieval of the forms to return to the model from the
 * `find-forms` MCP tool, so it renders a form by the visitor's INTENT rather than
 * the form's title. Mirrors {@see ToolHybridSearch}: a dense (L2) and a
 * keyword (LexicalSearch) channel over an agent's active forms, fused with RRF
 * (k = 60). An agent has a few dozen forms at most, so both channels score
 * exactly the agent's own forms (`form_definitions` has no vector index).
 *
 * The common case — an agent with only a handful of forms — never pays for the
 * embedding round-trip: {@see self::forPrompt()} returns the whole set wholesale.
 * Hybrid retrieval only kicks in once a form catalog outgrows the result budget,
 * and degrades to an alphabetical slice if embeddings aren't populated yet or the
 * query embedding fails — a form is never silently invisible.
 */
class FormHybridSearch
{
    /**
     * RRF constant. 60 is the de-facto default (Cormack et al.) — it smooths
     * per-channel rank differences without letting any single top hit dominate.
     */
    private const int RRF_K = 60;

    /** Most forms to ever name in one system prompt (the cap + the retrieval K). */
    public const int PROMPT_LIMIT = 8;

    public function __construct(
        private readonly EmbeddingsProvider $embeddings,
        private readonly EmbeddingStorage $storage,
        private readonly LexicalSearch $lexical,
    ) {}

    /**
     * The active forms to name in the system prompt for this turn. At most
     * `$limit` rows: the whole set when it fits, else the `$limit` most relevant
     * to the visitor's message.
     *
     * @return Collection<int, FormDefinition>
     */
    public function forPrompt(Agent $agent, string $message, int $limit = self::PROMPT_LIMIT): Collection
    {
        // One query covers the common case. Fetch one more than the budget: if
        // the agent has at most `$limit` active forms they all fit, so list them
        // wholesale (deterministic, no embedding call, works pre-backfill).
        $active = FormDefinition::query()
            ->forAgent($agent)
            ->where('is_active', true)
            ->unmanaged()
            ->orderBy('name')
            ->limit($limit + 1)
            ->get(['id', 'slug', 'name', 'agent_instructions']);

        if ($active->count() <= $limit) {
            return $active;
        }

        // More forms than fit — rank by relevance to the message. Fall back to
        // the alphabetical slice if retrieval comes back empty (no embeddings).
        $ranked = $this->search($agent, $message, $limit);

        return $ranked->isNotEmpty() ? $ranked : $active->take($limit)->values();
    }

    /**
     * Top-`$limit` active forms for the agent by RRF over the dense + sparse
     * channels, in score order. Empty if the agent has no embedded forms or the
     * query embedding fails (a flaky embedder must not break the chat turn).
     *
     * @return Collection<int, FormDefinition>
     */
    public function search(Agent $agent, string $message, int $limit = self::PROMPT_LIMIT): Collection
    {
        try {
            $queryVector = $this->embeddings->embedQuery($message);
        } catch (Throwable $e) {
            Log::warning('Form hybrid search: query embedding failed', [
                'agent_id' => $agent->id,
                'error' => $e->getMessage(),
            ]);

            return collect();
        }

        // embedQuery() is typed string|array; the dense channel needs the float
        // array. Bail to the caller's fallback rather than coerce a stray string.
        if (! is_array($queryVector)) {
            return collect();
        }

        $vectorString = '['.implode(',', $queryVector).']';
        $candidateLimit = max($limit * 3, $limit);
        // The install's column type (e.g. halfvec(1024)), so the query vector
        // compares with the stored ones whatever size the install chose.
        $cast = $this->storage->columnType();
        $rrfK = self::RRF_K;

        // The agent's active forms are collected once (MATERIALIZED, from the
        // agent_id key) and scored on both channels by exact distance. A
        // keyword score of 0 means no shared term: not a keyword hit.
        $sql = <<<SQL
WITH scope AS MATERIALIZED (
    SELECT fd.id,
           fd.embedding <-> q.vector AS vector_dist,
           NULLIF({$this->lexical->score('fd.search_text', 'q.terms')}, 0) AS bm25_dist
    FROM (
        SELECT ?::{$cast} AS vector,
               {$this->lexical->queryExpression('form_definitions_search_text_lexical')} AS terms
    ) q
    CROSS JOIN form_definitions fd
    WHERE fd.agent_id = ?
      AND fd.is_active = true
      AND fd.managed_by IS NULL
),
form_vector AS (
    SELECT id, ROW_NUMBER() OVER (ORDER BY vector_dist, id) AS rank
    FROM scope
    WHERE vector_dist IS NOT NULL
    ORDER BY vector_dist, id
    LIMIT ?
),
form_bm25 AS (
    SELECT id, ROW_NUMBER() OVER (ORDER BY bm25_dist, id) AS rank
    FROM scope
    WHERE bm25_dist IS NOT NULL
    ORDER BY bm25_dist, id
    LIMIT ?
),
combined AS (
    SELECT id, rank FROM form_vector
    UNION ALL
    SELECT id, rank FROM form_bm25
),
rrf AS (
    SELECT id, SUM(1.0 / ({$rrfK} + rank)) AS score
    FROM combined
    GROUP BY id
)
SELECT id FROM rrf ORDER BY score DESC, id LIMIT ?
SQL;

        try {
            $rows = DB::select($sql, [
                $vectorString, $this->lexical->queryText($message), $agent->id, // scope
                $candidateLimit,                     // form_vector
                $candidateLimit,                     // form_bm25
                $limit,                              // final LIMIT
            ]);
        } catch (Throwable $e) {
            Log::warning('Form hybrid search: query failed', [
                'agent_id' => $agent->id,
                'error' => $e->getMessage(),
            ]);

            return collect();
        }

        $ids = array_map(static fn (object $row): int => (int) $row->id, $rows);
        if ($ids === []) {
            return collect();
        }

        // Reload the rows once, then re-order to the RRF score order the SQL
        // returned (a plain whereIn loses it). Re-scope to the agent defensively —
        // the ids are already agent-scoped by the CTEs, this just keeps the guard
        // local if that ever changes.
        $byId = FormDefinition::query()
            ->forAgent($agent)
            ->whereIn('id', $ids)
            ->get(['id', 'slug', 'name', 'agent_instructions'])
            ->keyBy('id');

        return collect($ids)
            ->map(static fn (int $id): ?FormDefinition => $byId->get($id))
            ->filter()
            ->values();
    }
}
