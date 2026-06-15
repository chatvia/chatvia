<?php

namespace Domain\Conversation\Services\Tooling\Routing;

use Domain\Conversation\Services\Tooling\Registry\ToolEntry;
use Domain\Knowledge\Embedding\Contracts\EmbeddingsProviderInterface;
use Domain\Knowledge\Ingestion\Services\Search\KnowledgeBase;
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
 * It is the tool-side mirror of {@see KnowledgeBase}'s
 * rerank gate (config: chatvia.vector.search.relevance_threshold), which already
 * keeps greetings from pulling in irrelevant knowledge chunks.
 *
 * Degradation: any reranker failure — or a provider with no reranker — returns the
 * candidates unchanged. Routing is a quality optimisation, never a correctness
 * dependency; a flaky reranker must not strand a turn that legitimately needs its
 * tools.
 */
class ToolRelevanceGate
{
    public function __construct(
        private readonly EmbeddingsProviderInterface $embeddings,
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
        if (! $this->embeddings->supportsReranking()) {
            return $candidates;
        }

        $threshold = (float) config('chatvia.agent.tools.routing.relevance_threshold', 0.55);
        $candidateLimit = max((int) config('chatvia.agent.tools.routing.rerank_candidates', 12), 1);

        $pool = array_slice(array_values($candidates), 0, $candidateLimit);
        $documents = array_map(static fn (ToolEntry $e): string => self::document($e), $pool);

        try {
            // top_n = candidate count so we get a score for every candidate and do
            // the cutoff ourselves; the reranker tokens attribute to the workspace
            // via the tool-routing usage scope ToolSelector already opened.
            $ranked = $this->embeddings->rerank($userMessage, $documents, count($documents));
        } catch (Throwable $e) {
            Log::warning('ToolRelevanceGate: rerank failed; shipping candidates ungated', [
                'candidates' => count($pool),
                'error' => $e->getMessage(),
            ]);

            return $candidates;
        }

        // Provider declined mid-flight — keep recall-only rather than drop everything.
        if ($ranked === null) {
            return $candidates;
        }

        $kept = [];
        $dropped = [];

        foreach ($ranked as $item) {
            $entry = $pool[$item['index']] ?? null;
            if ($entry === null) {
                continue;
            }

            if ((float) $item['relevance_score'] >= $threshold) {
                $kept[] = $entry;

                continue;
            }

            $dropped[] = ['name' => $entry->name, 'score' => round((float) $item['relevance_score'], 4)];
        }

        Log::debug('ToolRelevanceGate: gate complete', [
            'threshold' => $threshold,
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
    private static function document(ToolEntry $entry): string
    {
        return trim($entry->name."\n".$entry->description);
    }
}
