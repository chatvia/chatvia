<?php

namespace Domain\Integration\Tool\Routing;

use Domain\Integration\Tool\Data\ToolEntry;
use Domain\Integration\Tool\Search\ToolHybridSearch;
use Domain\Knowledge\Embedding\Contracts\Reranker;
use Domain\Knowledge\Embedding\Data\RerankedDocumentData;
use Domain\Knowledge\Embedding\Services\RelevanceThresholds;
use Domain\Knowledge\Retrieval\Services\KnowledgeBase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
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
 * candidate against the user's actual message; anything below its bar is dropped.
 * Without a reranker (or when it fails) the cosine similarity of each tool's
 * embedding to the message is the signal instead (the basic similarity check).
 * On a greeting or an acknowledgement nothing clears the bar, the gate returns
 * [], and the caller ships zero tools — the `tools` array is omitted from the
 * LLM request entirely, so the model can't see, list, or advertise a tool that
 * isn't relevant to the turn.
 *
 * The bar is per-scope: write/delete tools ({@see ToolEntry::$mutates}) get the
 * write bar, read-only tools the read bar. A missed read just costs the user a
 * re-ask; a wrong write executes against the wrong system. Each reranking or
 * embedding model scores on its own scale, so the bars are the model's
 * measured ones unless config overrides them ({@see RelevanceThresholds}).
 *
 * It is the tool-side mirror of {@see KnowledgeBase}'s relevance check, which
 * keeps greetings from pulling in irrelevant knowledge chunks.
 *
 * Degradation: with neither a reranker nor a measured similarity bar, the top
 * candidates pass ungated. Routing is a quality optimisation, never a
 * correctness dependency; a flaky reranker must not strand a turn that
 * legitimately needs its tools.
 */
class ToolRelevanceGate
{
    public function __construct(
        private readonly Reranker $reranker,
    ) {}

    /**
     * @param  array<int, ToolEntry>  $candidates  RRF-ranked, strongest first.
     * @param  array<string, float>  $similarities  each candidate's similarity to the message, by `sourceId`
     * @return array<int, ToolEntry> The relevant subset, strongest first.
     */
    public function gate(string $userMessage, array $candidates, array $similarities = []): array
    {
        if ($candidates === []) {
            return [];
        }

        // Master switch: off = recall-only, the old availability-driven behaviour.
        if (! config('chatvia.agent.tools.routing.relevance_gate', true)) {
            return $candidates;
        }

        $candidateLimit = max((int) config('chatvia.agent.tools.routing.rerank_candidates', 12), 1);
        $pool = array_slice(array_values($candidates), 0, $candidateLimit);

        if ($this->reranker->isEnabled()) {
            try {
                // top_n = candidate count so we get a score for every candidate and do
                // the cutoff ourselves.
                return $this->keepRelevant($pool, $this->reranker->rerank($userMessage, array_map($this->document(...), $pool), count($pool)));
            } catch (Throwable $e) {
                Log::warning('ToolRelevanceGate: rerank failed; using the similarity check', [
                    'candidates' => count($pool),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $this->bySimilarity($pool, $similarities);
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
        $readThreshold = RelevanceThresholds::toolRerank($this->reranker, mutates: false);
        $writeThreshold = RelevanceThresholds::toolRerank($this->reranker, mutates: true);

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
            'check' => 'rerank',
            'read_threshold' => $readThreshold,
            'write_threshold' => $writeThreshold,
            'candidates' => count($pool),
            'kept' => array_map(static fn (ToolEntry $e): string => $e->name, $kept),
            'dropped' => $dropped,
        ]);

        return $kept;
    }

    /**
     * The basic similarity check: keep the candidates whose similarity to the
     * message clears their scope's bar, in the search's order. With no
     * measured bar (or no similarities), the pool passes ungated.
     *
     * @param  array<int, ToolEntry>  $pool
     * @param  array<string, float>  $similarities
     * @return array<int, ToolEntry>
     */
    private function bySimilarity(array $pool, array $similarities): array
    {
        $readThreshold = RelevanceThresholds::toolSimilarity(mutates: false);
        $writeThreshold = RelevanceThresholds::toolSimilarity(mutates: true);

        if ($readThreshold === null || $writeThreshold === null || $similarities === []) {
            return $pool;
        }

        $kept = array_values(array_filter(
            $pool,
            static fn (ToolEntry $entry): bool => ($similarities[$entry->sourceId] ?? 0.0) >= ($entry->mutates ? $writeThreshold : $readThreshold),
        ));

        Log::debug('ToolRelevanceGate: gate complete', [
            'check' => 'similarity',
            'read_threshold' => $readThreshold,
            'write_threshold' => $writeThreshold,
            'candidates' => count($pool),
            'kept' => array_map(static fn (ToolEntry $e): string => $e->name, $kept),
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
        return Str::trim($entry->name."\n".$entry->description);
    }
}
