<?php

namespace Domain\Form\Services;

use Domain\Agent\Models\Agent;
use Domain\Conversation\Services\Tooling\Routing\ToolHybridSearch;
use Domain\Form\Models\FormDefinition;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Per-turn retrieval of the forms to surface to the model, so it renders a form
 * by the visitor's INTENT rather than the form's title. Mirrors
 * {@see ToolHybridSearch}: a
 * dense (vchordrq, L2) and a sparse (BM25, llmlingua2) channel over an agent's
 * active forms, fused with RRF (k = 60).
 *
 * The common case — an agent with only a handful of forms — never pays for the
 * embedding round-trip: {@see self::forPrompt()} lists the whole set wholesale.
 * Hybrid retrieval only kicks in once a form catalog outgrows the prompt
 * budget, and degrades to an alphabetical slice if embeddings aren't populated
 * yet or the query embedding fails — a form is never silently invisible.
 */
class FormHybridSearch
{
    /** vchordrq + BM25 indexes were built against this dim. */
    private const EMBEDDING_DIMS = 1024;

    /**
     * RRF constant. 60 is the de-facto default (Cormack et al.) — it smooths
     * per-channel rank differences without letting any single top hit dominate.
     */
    private const RRF_K = 60;

    /** Most forms to ever name in one system prompt (the cap + the retrieval K). */
    public const PROMPT_LIMIT = 8;

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
            ->orderBy('name')
            ->limit($limit + 1)
            ->get(['id', 'slug', 'name', 'description']);

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
            $queryVector = embed_query($message);
        } catch (Throwable $e) {
            Log::warning('Form hybrid search: query embedding failed', [
                'agent_id' => $agent->id,
                'error' => $e->getMessage(),
            ]);

            return collect();
        }

        // embed_query() is typed string|array; the dense channel needs the float
        // array. Bail to the caller's fallback rather than coerce a stray string.
        if (! is_array($queryVector)) {
            return collect();
        }

        $vectorString = '['.implode(',', $queryVector).']';
        $candidateLimit = max($limit * 3, $limit);
        $dims = self::EMBEDDING_DIMS;
        $rrfK = self::RRF_K;

        $sql = <<<SQL
WITH form_vector AS (
    SELECT fd.id,
           ROW_NUMBER() OVER (ORDER BY fd.embedding <-> ?::vector({$dims})) AS rank
    FROM form_definitions fd
    WHERE fd.agent_id = ?
      AND fd.is_active = true
      AND fd.embedding IS NOT NULL
    ORDER BY fd.embedding <-> ?::vector({$dims})
    LIMIT ?
),
form_bm25 AS (
    SELECT fd.id,
           ROW_NUMBER() OVER (
               ORDER BY fd.embedding_bm25 <&> bm25_catalog.to_bm25query(
                   'form_definitions_embedding_bm25',
                   tokenizer_catalog.tokenize(?::text, 'llmlingua2')
               )
           ) AS rank
    FROM form_definitions fd
    WHERE fd.agent_id = ?
      AND fd.is_active = true
      AND fd.embedding_bm25 IS NOT NULL
    ORDER BY rank
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
SELECT id FROM rrf ORDER BY score DESC LIMIT ?
SQL;

        try {
            $rows = DB::select($sql, [
                $vectorString, $agent->id, $vectorString, $candidateLimit, // form_vector
                $message, $agent->id, $candidateLimit,                     // form_bm25
                $limit,                                                     // final LIMIT
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
            ->get(['id', 'slug', 'name', 'description'])
            ->keyBy('id');

        return collect($ids)
            ->map(static fn (int $id): ?FormDefinition => $byId->get($id))
            ->filter()
            ->values();
    }
}
