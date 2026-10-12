<?php

namespace Domain\Knowledge\Ingestion\QueryBuilders;

use Domain\Agent\Models\Agent;
use Domain\Knowledge\Ingestion\Models\Chunk;
use Domain\Knowledge\Retrieval\Queries\HybridChunkSearch;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Support\Facades\DB;

/**
 * Queries over knowledge chunks: agent and source scoping, website chunks no
 * page links to any more, and the question/content samples used by the Setup
 * and Evals pages. The knowledge-base search itself lives in
 * {@see HybridChunkSearch}.
 *
 * @template TModel of Chunk
 *
 * @extends Builder<TModel>
 */
class ChunkQueryBuilder extends Builder
{
    /** How many question-bearing chunks are read when mining questions. */
    private const int QUESTION_CHUNKS_SCANNED = 200;

    /** Longest question kept: starters and eval case names are 255-char columns. */
    private const int MAX_QUESTION_LENGTH = 255;

    /** Characters kept per chunk in a content sample, so a sample fits one LLM call. */
    private const int SAMPLE_CHARS = 600;

    /**
     * Only the chunks of sources the agent uses (its `agent_sources` rows).
     * Chunks belong to sources, not agents, so this is how agent scoping works
     * everywhere; the hybrid search inlines the same filter in its SQL. The
     * workspace filter keeps the lookup on the workspace's own chunks.
     *
     * @return self<TModel>
     */
    public function forAgent(Agent $agent): self
    {
        return $this
            ->where('chunks.workspace_id', $agent->workspace_id)
            ->whereExists(function (QueryBuilder $query) use ($agent): void {
                $query->select(DB::raw(1))
                    ->from('agent_sources as s')
                    ->whereColumn('s.sourceable_id', 'chunks.chunkable_id')
                    ->whereColumn('s.sourceable_type', 'chunks.chunkable_type')
                    ->where('s.agent_id', $agent->id);
            });
    }

    /**
     * Every chunk of one source, its summary chunk (index 0) included.
     *
     * @return self<TModel>
     */
    public function ofSource(Model $source): self
    {
        return $this
            ->where('chunks.chunkable_type', $source->getMorphClass())
            ->where('chunks.chunkable_id', $source->getKey());
    }

    /**
     * Page chunks that no `chunk_references` row points at any more. Website
     * chunks are shared by the pages that produced them, so a chunk is only
     * dead once its last page is gone. The summary chunk (index 0) never has
     * references and is kept.
     *
     * @return self<TModel>
     */
    public function unreferenced(): self
    {
        return $this
            ->whereNot('chunks.chunk_index', 0)
            ->whereNotExists(function (QueryBuilder $query): void {
                $query->select(DB::raw(1))
                    ->from('chunk_references as r')
                    ->whereColumn('r.chunk_id', 'chunks.id');
            });
    }

    /**
     * Chunks that carry LLM-generated questions (a newline-delimited block).
     *
     * @return self<TModel>
     */
    public function withQuestions(): self
    {
        return $this->whereNotNull('questions')->where('questions', '!=', '');
    }

    /**
     * Distinct candidate questions for an agent, drawn from the LLM-generated
     * `questions` on its ingested chunks (each chunk holds a newline-delimited
     * block). Used to suggest conversation starters in the agent Setup UI.
     * Scoped to the agent with forAgent(). Returns an empty array when the
     * agent has no question-bearing chunks.
     *
     * @return array<int, string>
     */
    public function questionsForAgent(Agent $agent, int $limit = 60): array
    {
        $blocks = $this->getModel()->newQuery()
            ->withQuestions()
            ->forAgent($agent)
            ->limit(self::QUESTION_CHUNKS_SCANNED)
            ->pluck('questions');

        return $blocks
            ->flatMap(fn (string $block): array => preg_split('/\r\n|\r|\n/', $block) ?: [])
            ->map(fn (string $question): string => trim($question))
            ->filter()
            // The starters endpoint caps each entry at 255 chars; never suggest
            // one that would fail validation when the user saves it.
            ->filter(fn (string $question): bool => mb_strlen($question) <= self::MAX_QUESTION_LENGTH)
            ->unique()
            ->take($limit)
            ->values()
            ->all();
    }

    /**
     * Like questionsForAgent, but keeps each question's owning chunk — the
     * Evals "From knowledge" picker needs the linkage to fetch the chunk text
     * as a reference answer at case-creation time. Splitting happens in PHP
     * because each chunk holds a newline-delimited block of questions.
     *
     * @return list<array{chunk_id: int, question: string, source_type: string}>
     */
    public function questionCandidatesForAgent(Agent $agent, int $limit = 200): array
    {
        $rows = $this->getModel()->newQuery()
            ->withQuestions()
            ->forAgent($agent)
            ->limit(self::QUESTION_CHUNKS_SCANNED)
            ->get(['id', 'questions', 'chunkable_type']);

        $seen = [];
        $candidates = [];

        foreach ($rows as $row) {
            foreach (preg_split('/\r\n|\r|\n/', (string) $row->questions) ?: [] as $question) {
                $question = trim($question);

                // Same 255-char cap as questionsForAgent — here because the
                // question doubles as the eval case name, a 255-char column.
                if ($question === '' || mb_strlen($question) > self::MAX_QUESTION_LENGTH || isset($seen[mb_strtolower($question)])) {
                    continue;
                }

                $seen[mb_strtolower($question)] = true;
                $candidates[] = ['chunk_id' => $row->id, 'question' => $question, 'source_type' => $row->chunkable_type];

                if (count($candidates) >= $limit) {
                    return $candidates;
                }
            }
        }

        return $candidates;
    }

    /**
     * Whether the agent has any ingested knowledge-base content (chunks). Gates
     * the Setup → Starters "Generate with AI" button.
     */
    public function hasContentForAgent(Agent $agent): bool
    {
        return $this->getModel()->newQuery()->forAgent($agent)->exists();
    }

    /**
     * A representative text sample of the agent's knowledge base — each chunk's
     * summary, falling back to its body — for grounding LLM starter generation.
     * Each entry is capped so the combined sample stays within one LLM call.
     *
     * @return array<int, string>
     */
    public function contentSampleForAgent(Agent $agent, int $limit = 30): array
    {
        return $this->getModel()->newQuery()
            ->forAgent($agent)
            ->limit($limit)
            ->get(['summary', 'chunk'])
            ->map(fn ($row): string => trim((string) ($row->summary ?: $row->chunk)))
            ->filter()
            ->map(fn (string $text): string => mb_substr($text, 0, self::SAMPLE_CHARS))
            ->values()
            ->all();
    }
}
