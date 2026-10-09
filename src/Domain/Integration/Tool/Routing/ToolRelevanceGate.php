<?php

namespace Domain\Integration\Tool\Routing;

use Domain\Integration\Tool\Data\ToolEntry;
use Domain\Integration\Tool\Search\ToolHybridSearch;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Embedding\Data\RerankedDocumentData;
use Domain\Knowledge\Retrieval\Services\KnowledgeBase;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Precision gate over the hybrid-search candidate set.
 *
 * {@see ToolHybridSearch} ranks tools by RRF — a rank-fusion score that is strong
 * for *ordering* but carries no absolute "is this actually relevant" signal: the
 * top tool scores about the same for "hi" as for "what's the weather in
 * Copenhagen". With nothing to threshold on, every tool-bearing turn ships its top
 * hits and the model happily advertises them — tool discovery driven by
 * availability rather than demand.
 *
 * This gate adds the missing absolute signal. A cross-encoder reranker scores each
 * candidate against the user's actual message; anything below the configured
 * relevance threshold is dropped. On a greeting or an acknowledgement nothing
 * clears the bar, the gate returns [], and the caller ships zero tools — the
 * `tools` array is omitted from the LLM request entirely, so the model can't see,
 * list, or advertise a tool that isn't relevant to the turn.
 *
 * The bar is per-scope: write/delete tools ({@see ToolEntry::$mutates}) are held to
 * `relevance_threshold_write`, read-only tools to `relevance_threshold_read`. A
 * missed read just costs the user a re-ask; a wrong write executes against the
 * wrong system — so the write set is biased toward precision (fewer, surer tools)
 * and the read set toward recall. Both default to the shared `relevance_threshold`,
 * so the gate stays symmetric until the two are deliberately split.
 *
 * It is the tool-side mirror of {@see KnowledgeBase}'s
 * rerank gate (config: chatvia.vector.search.relevance_threshold), which already
 * keeps greetings from pulling in irrelevant knowledge chunks.
 *
 * Degradation: any reranker failure — or an install with no reranker — returns the
 * candidates unchanged. Routing is a quality optimisation, never a correctness
 * dependency; a flaky reranker must not strand a turn that legitimately needs its
 * tools.
 */
class ToolRelevanceGate
{
    public function __construct(
        private readonly Reranker $reranker,
    ) {}

    /**
     * @param  array<int, ToolEntry>  $candidates  RRF-ranked, strongest first.
     * @return array<int, ToolEntry> The relevant subset, reranked strongest first.
     */
    public function gate(string $userMessage, array $candidates): array
    {
        if ($candidates === []) {
            return [];
        }

        // Master switch: off = recall-only, the old availability-driven behaviour.
        if (! config('chatvia.agent.tools.routing.relevance_gate', true)) {
            return $candidates;
        }

        // No reranker = no absolute signal to gate on. Keep the recall-only set
        // rather than guessing with the unthresholdable RRF score.
        if (! $this->reranker->isEnabled()) {
            return $candidates;
        }

        $candidateLimit = max((int) config('chatvia.agent.tools.routing.rerank_candidates', 12), 1);
        $pool = array_slice(array_values($candidates), 0, $candidateLimit);
        $documents = array_map($this->document(...), $pool);

        try {
            // top_n = candidate count so we get a score for every candidate and do
            // the cutoff ourselves; the reranker tokens attribute to the workspace
            // via the tool-routing usage scope the turn's tool selection opened.
            $ranked = $this->reranker->rerank($userMessage, $documents, count($documents));
        } catch (Throwable $e) {
            Log::warning('ToolRelevanceGate: rerank failed; shipping candidates ungated', [
                'candidates' => count($pool),
                'error' => $e->getMessage(),
            ]);

            return $candidates;
        }

        return $this->keepRelevant($pool, $ranked);
    }

    /**
     * Keep the candidates whose reranker score clears their scope's bar.
     *
     * @param  array<int, ToolEntry>  $pool
     * @param  list<RerankedDocumentData>  $ranked
     * @return array<int, ToolEntry>
     */
    private function keepRelevant(array $pool, array $ranked): array
    {
        // Per-scope bars: a wrong read is cheap, a wrong write executes — so write
        // tools are held to a stricter relevance score than read tools. Both fall
        // back to the shared `relevance_threshold` (so the gate is symmetric until
        // the two are deliberately split).
        $fallback = (float) config('chatvia.agent.tools.routing.relevance_threshold', 0.55);
        $readThreshold = (float) config('chatvia.agent.tools.routing.relevance_threshold_read', $fallback);
        $writeThreshold = (float) config('chatvia.agent.tools.routing.relevance_threshold_write', $fallback);

        $kept = [];
        $dropped = [];

        foreach ($ranked as $item) {
            $entry = $pool[$item->index] ?? null;
            if ($entry === null) {
                continue;
            }

            $bar = $entry->mutates ? $writeThreshold : $readThreshold;
            $score = $item->relevanceScore;

            if ($score >= $bar) {
                $kept[] = $entry;

                continue;
            }

            $dropped[] = [
                'name' => $entry->name,
                'score' => round($score, 4),
                'scope' => $entry->mutates ? 'write' : 'read',
                'bar' => $bar,
            ];
        }

        Log::debug('ToolRelevanceGate: gate complete', [
            'read_threshold' => $readThreshold,
            'write_threshold' => $writeThreshold,
            'candidates' => count($pool),
            'kept' => array_map(static fn (ToolEntry $e): string => $e->name, $kept),
            'dropped' => $dropped,
        ]);

        return $kept;
    }

    /**
     * The text the reranker scores against the user message. The compiled name
     * carries keyword signal (`mcp__github__search_issues`), the description
     * carries intent — both help the cross-encoder.
     */
    private function document(ToolEntry $entry): string
    {
        return trim($entry->name."\n".$entry->description);
    }
}
